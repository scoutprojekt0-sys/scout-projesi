<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AiLabelingController extends Controller
{
    private const SPORT_KEYWORDS = [
        'football' => ['football', 'futbol', 'soccer'],
        'basketball' => ['basketball', 'basketbol'],
        'volleyball' => ['volleyball', 'voleybol', 'voleyball'],
    ];

    public function queue(Request $request, string $sport): JsonResponse
    {
        $sport = $this->normalizeSport($sport);
        $split = strtolower(trim((string) $request->query('split', 'train')));
        $latestOnly = filter_var($request->query('latest_only', false), FILTER_VALIDATE_BOOL);
        if (! in_array($split, ['train', 'val', 'test', 'all'], true)) {
            return response()->json(['ok' => false, 'message' => 'Gecersiz split'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $suffix = $split === 'all' ? 'all' : $split;
        $queuePath = base_path("ai-worker/datasets/{$sport}/queues/label_queue_{$suffix}.csv");
        if (! File::exists($queuePath)) {
            return response()->json(['ok' => false, 'message' => 'Queue dosyasi bulunamadi'], Response::HTTP_NOT_FOUND);
        }

        $lines = file($queuePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $skipped = $this->loadSkippedItems($sport, $split);
        $rows = [];
        $latestSourceKey = null;
        $latestSourceMtime = null;
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $data = str_getcsv($line);
            if (count($data) < 4) {
                continue;
            }

            [$rowSplit, $imagePath, $labelPath, $status] = $data;
            $imagePath = str_replace('\\', '/', $imagePath);
            if (isset($skipped[$imagePath])) {
                continue;
            }
            if ($this->isMismatchedForSport($sport, implode(' ', [$imagePath, $labelPath]))) {
                continue;
            }
            $rows[] = [
                'id' => md5($imagePath),
                'split' => $rowSplit,
                'image_path' => $imagePath,
                'label_path' => $labelPath,
                'status' => $status,
                'source_key' => $this->extractSourceKey($imagePath),
                'image_url' => '/api/ai-labeling/image?path='.urlencode($imagePath),
            ];

            $mtime = @filemtime($imagePath);
            if ($mtime !== false && ($latestSourceMtime === null || $mtime > $latestSourceMtime)) {
                $latestSourceMtime = $mtime;
                $latestSourceKey = $this->extractSourceKey($imagePath);
            }
        }

        if ($latestOnly && $latestSourceKey !== null) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['source_key'] ?? null) === $latestSourceKey
            ));
        }

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'meta' => [
                'latest_source_key' => $latestSourceKey,
                'latest_only' => $latestOnly,
            ],
        ]);
    }

    public function image(Request $request): BinaryFileResponse
    {
        $path = (string) $request->query('path', '');
        $resolved = $this->validateDatasetPath($path, '/images/');
        abort_unless(File::exists($resolved), 404, 'Gorsel bulunamadi');

        return response()->file($resolved);
    }

    public function save(Request $request, string $sport): JsonResponse
    {
        $sport = $this->normalizeSport($sport);
        $validated = $request->validate([
            'image_path' => ['required', 'string'],
            'label_path' => ['required', 'string'],
            'boxes' => ['required', 'array'],
            'boxes.*.class_id' => ['required', 'integer', 'min:0', 'max:3'],
            'boxes.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'boxes.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'boxes.*.w' => ['required', 'numeric', 'min:0', 'max:1'],
            'boxes.*.h' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $imagePath = $this->validateDatasetPath($validated['image_path'], '/images/');
        $labelPath = $this->validateDatasetPath($validated['label_path'], '/labels/');
        abort_unless(str_contains($imagePath, "/{$sport}/"), 422, 'Sport klasoru eslesmiyor');
        abort_unless(str_contains($labelPath, "/{$sport}/"), 422, 'Sport klasoru eslesmiyor');

        $lines = collect($validated['boxes'])
            ->map(function (array $box): string {
                return implode(' ', [
                    (int) $box['class_id'],
                    $this->formatFloat((float) $box['x']),
                    $this->formatFloat((float) $box['y']),
                    $this->formatFloat((float) $box['w']),
                    $this->formatFloat((float) $box['h']),
                ]);
            })
            ->implode(PHP_EOL);

        File::ensureDirectoryExists(dirname($labelPath));
        File::put($labelPath, $lines.(($lines !== '') ? PHP_EOL : ''));
        $split = $this->extractSplitFromPath($imagePath);
        $this->removeSkippedItem($sport, $split, $imagePath);
        $this->removeFromQueueFiles($sport, $split, $imagePath);

        return response()->json([
            'ok' => true,
            'message' => 'Label kaydedildi.',
            'data' => [
                'image_path' => $imagePath,
                'label_path' => $labelPath,
                'box_count' => count($validated['boxes']),
            ],
        ]);
    }

    public function predict(Request $request, string $sport): JsonResponse
    {
        $sport = $this->normalizeSport($sport);
        $validated = $request->validate([
            'image_path' => ['required', 'string'],
            'conf' => ['nullable', 'numeric', 'min:0.01', 'max:0.95'],
        ]);

        $imagePath = $this->validateDatasetPath($validated['image_path'], '/images/');
        abort_unless(str_contains($imagePath, "/{$sport}/"), 422, 'Sport klasoru eslesmiyor');

        $modelPath = $this->resolvePredictModelPath($sport);
        if ($modelPath === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Tahmin modeli bulunamadi. Once egitim cikti dosyasini olustur.',
            ], Response::HTTP_NOT_FOUND);
        }

        $pythonPath = base_path('ai-worker/.venv/Scripts/python.exe');
        if (! File::exists($pythonPath)) {
            $pythonPath = 'python';
        }

        $process = new Process([
            $pythonPath,
            base_path('ai-worker/scripts/predict_boxes.py'),
            '--model',
            $modelPath,
            '--image',
            $imagePath,
            '--conf',
            (string) ($validated['conf'] ?? 0.20),
            '--max-det',
            '80',
        ], base_path());
        $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';
        $windir = getenv('WINDIR') ?: $systemRoot;
        $path = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');
        $userProfile = getenv('USERPROFILE') ?: 'C:\\Users\\Hp';
        $tempPath = getenv('TEMP') ?: $userProfile.'\\AppData\\Local\\Temp';
        $process->setEnv([
            'POLARS_SKIP_CPU_CHECK' => '1',
            'PATH' => dirname($pythonPath).PATH_SEPARATOR.$path,
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => $windir,
            'USERPROFILE' => $userProfile,
            'HOME' => $userProfile,
            'HOMEDRIVE' => getenv('HOMEDRIVE') ?: 'C:',
            'HOMEPATH' => getenv('HOMEPATH') ?: '\\Users\\Hp',
            'APPDATA' => getenv('APPDATA') ?: $userProfile.'\\AppData\\Roaming',
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: $userProfile.'\\AppData\\Local',
            'USERNAME' => getenv('USERNAME') ?: 'Hp',
            'USER' => getenv('USER') ?: 'Hp',
            'TEMP' => $tempPath,
            'TMP' => getenv('TMP') ?: $tempPath,
            'TORCHINDUCTOR_CACHE_DIR' => $tempPath.'\\torchinductor_Hp',
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return response()->json([
                'ok' => false,
                'message' => 'AI tahmin calismadi.',
                'error' => $this->safeProcessOutput($process->getErrorOutput() ?: $process->getOutput()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = $this->decodePredictOutput($process->getOutput());
        if ($payload === null) {
            return response()->json([
                'ok' => false,
                'message' => 'AI tahmin cevabi okunamadi.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'ok' => true,
            'data' => $payload,
            'model_path' => $modelPath,
        ]);
    }

    public function skip(Request $request, string $sport): JsonResponse
    {
        $sport = $this->normalizeSport($sport);
        $validated = $request->validate([
            'image_path' => ['required', 'string'],
            'label_path' => ['required', 'string'],
        ]);

        $imagePath = $this->validateDatasetPath($validated['image_path'], '/images/');
        $labelPath = $this->validateDatasetPath($validated['label_path'], '/labels/');
        abort_unless(str_contains($imagePath, "/{$sport}/"), 422, 'Sport klasoru eslesmiyor');
        abort_unless(str_contains($labelPath, "/{$sport}/"), 422, 'Sport klasoru eslesmiyor');

        $split = $this->extractSplitFromPath($imagePath);
        $this->storeSkippedItem($sport, $split, $imagePath);
        $this->removeFromQueueFiles($sport, $split, $imagePath);

        return response()->json([
            'ok' => true,
            'message' => 'Gorsel atlandi ve tekrar queueya alinmayacak.',
            'data' => [
                'image_path' => $imagePath,
                'label_path' => $labelPath,
                'split' => $split,
            ],
        ]);
    }

    private function normalizeSport(string $sport): string
    {
        $normalized = strtolower(trim($sport));
        abort_unless(in_array($normalized, ['football', 'basketball', 'volleyball'], true), 404, 'Sport bulunamadi');

        return $normalized;
    }

    private function isMismatchedForSport(string $sport, string $text): bool
    {
        $normalized = strtolower($text);
        $requestedKeywords = self::SPORT_KEYWORDS[$sport] ?? [];
        $forbiddenKeywords = [];

        foreach (self::SPORT_KEYWORDS as $currentSport => $keywords) {
            if ($currentSport === $sport) {
                continue;
            }

            $forbiddenKeywords = array_merge($forbiddenKeywords, $keywords);
        }

        $hasRequestedKeyword = collect($requestedKeywords)->contains(
            static fn (string $keyword): bool => str_contains($normalized, $keyword)
        );
        $hasForbiddenKeyword = collect($forbiddenKeywords)->contains(
            static fn (string $keyword): bool => str_contains($normalized, $keyword)
        );

        return $hasForbiddenKeyword && ! $hasRequestedKeyword;
    }

    private function validateDatasetPath(string $path, string $mustContain): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $real = realpath($normalized);
        if (! is_string($real)) {
            $real = $this->resolveLegacyDatasetPath($normalized);
        }
        abort_unless(is_string($real), 404, 'Dosya yolu bulunamadi');

        $datasetRoot = str_replace('\\', '/', realpath(base_path('ai-worker/datasets')) ?: '');
        $realNormalized = str_replace('\\', '/', $real);

        abort_unless($datasetRoot !== '' && str_starts_with($realNormalized, $datasetRoot), 403, 'Dosya yolu gecersiz');
        abort_unless(str_contains($realNormalized, $mustContain), 403, 'Beklenmeyen dosya tipi');

        return $realNormalized;
    }

    private function resolveLegacyDatasetPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $marker = '/ai-worker/datasets/';
        $markerPosition = strpos($normalized, $marker);
        if ($markerPosition === false) {
            return null;
        }

        $relativePath = substr($normalized, $markerPosition + 1);
        if (! is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $candidate = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $resolved = realpath($candidate);

        return is_string($resolved) ? $resolved : null;
    }

    private function formatFloat(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function extractSplitFromPath(string $imagePath): string
    {
        if (preg_match('#/images/(train|val|test)/#', $imagePath, $matches) === 1) {
            return $matches[1];
        }

        abort(422, 'Split klasoru cozumlenemedi');
    }

    private function extractSourceKey(string $imagePath): string
    {
        $basename = pathinfo($imagePath, PATHINFO_FILENAME);
        $normalized = preg_replace('/\.f\d+_s\d+_\d+$/', '', $basename);

        return is_string($normalized) && $normalized !== '' ? $normalized : $basename;
    }

    private function skippedItemsPath(string $sport, string $split): string
    {
        return base_path("ai-worker/datasets/{$sport}/queues/skipped_{$split}.txt");
    }

    private function loadSkippedItems(string $sport, string $split): array
    {
        $splits = $split === 'all' ? ['train', 'val', 'test'] : [$split];
        $items = [];

        foreach ($splits as $currentSplit) {
            $path = $this->skippedItemsPath($sport, $currentSplit);
            if (! File::exists($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $normalized = str_replace('\\', '/', trim($line));
                if ($normalized !== '') {
                    $items[$normalized] = true;
                }
            }
        }

        return $items;
    }

    private function storeSkippedItem(string $sport, string $split, string $imagePath): void
    {
        $items = $this->loadSkippedItems($sport, $split);
        $items[str_replace('\\', '/', $imagePath)] = true;

        $path = $this->skippedItemsPath($sport, $split);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, array_keys($items)).PHP_EOL);
    }

    private function removeSkippedItem(string $sport, string $split, string $imagePath): void
    {
        $path = $this->skippedItemsPath($sport, $split);
        if (! File::exists($path)) {
            return;
        }

        $target = str_replace('\\', '/', $imagePath);
        $items = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            ->map(static fn (string $line): string => str_replace('\\', '/', trim($line)))
            ->filter(static fn (string $line): bool => $line !== '' && $line !== $target)
            ->values()
            ->all();

        if ($items === []) {
            File::delete($path);

            return;
        }

        File::put($path, implode(PHP_EOL, $items).PHP_EOL);
    }

    private function removeFromQueueFiles(string $sport, string $split, string $imagePath): void
    {
        foreach (["label_queue_{$split}.csv", 'label_queue_all.csv'] as $filename) {
            $path = base_path("ai-worker/datasets/{$sport}/queues/{$filename}");
            if (! File::exists($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
            if ($lines === []) {
                continue;
            }

            $filtered = [];
            foreach ($lines as $index => $line) {
                if ($index === 0) {
                    $filtered[] = $line;

                    continue;
                }

                $data = str_getcsv($line);
                if (count($data) < 2) {
                    continue;
                }

                $queuedImagePath = str_replace('\\', '/', $data[1]);
                if ($queuedImagePath === $imagePath) {
                    continue;
                }

                $filtered[] = $line;
            }

            File::put($path, implode(PHP_EOL, $filtered).PHP_EOL);
        }
    }

    private function resolvePredictModelPath(string $sport): ?string
    {
        $candidates = [];

        $modelMap = [
            'football' => base_path('ai-worker/models/football_player_ball.pt'),
            'basketball' => base_path('ai-worker/models/basketball_player_ball.pt'),
            'volleyball' => base_path('ai-worker/models/volleyball_player_ball.pt'),
        ];
        if (isset($modelMap[$sport]) && File::exists($modelMap[$sport])) {
            $candidates[] = $modelMap[$sport];
        }

        $runRoot = base_path("runs/{$sport}");
        if (File::exists($runRoot)) {
            foreach (File::allFiles($runRoot) as $file) {
                if ($file->getFilename() === 'best.pt') {
                    $candidates[] = $file->getPathname();
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $candidates[0];
    }

    private function decodePredictOutput(string $output): ?array
    {
        $lines = array_reverse(preg_split('/\R/', trim($output)) ?: []);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '{')) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded) && ($decoded['ok'] ?? false) === true) {
                return $decoded;
            }
        }

        return null;
    }

    private function safeProcessOutput(string $output): string
    {
        $clean = trim($output);
        if ($clean === '') {
            return '';
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $clean) ?? '';
    }
}
