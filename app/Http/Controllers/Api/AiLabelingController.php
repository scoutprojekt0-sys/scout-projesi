<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AiLabelingController extends Controller
{
    public function queue(Request $request, string $sport): JsonResponse
    {
        $sport = $this->normalizeSport($sport);
        $split = strtolower(trim((string) $request->query('split', 'train')));
        if (! in_array($split, ['train', 'val', 'test', 'all'], true)) {
            return response()->json(['ok' => false, 'message' => 'Gecersiz split'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $suffix = $split === 'all' ? 'all' : $split;
        $queuePath = base_path("ai-worker/datasets/{$sport}/queues/label_queue_{$suffix}.csv");
        if (! File::exists($queuePath)) {
            return response()->json(['ok' => false, 'message' => 'Queue dosyasi bulunamadi'], Response::HTTP_NOT_FOUND);
        }

        $lines = file($queuePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $rows = [];
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $data = str_getcsv($line);
            if (count($data) < 4) {
                continue;
            }

            [$rowSplit, $imagePath, $labelPath, $status] = $data;
            $rows[] = [
                'id' => md5($imagePath),
                'split' => $rowSplit,
                'image_path' => $imagePath,
                'label_path' => $labelPath,
                'status' => $status,
                'image_url' => '/api/ai-labeling/image?path='.urlencode($imagePath),
            ];
        }

        return response()->json(['ok' => true, 'data' => $rows]);
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

    private function normalizeSport(string $sport): string
    {
        $normalized = strtolower(trim($sport));
        abort_unless(in_array($normalized, ['football', 'basketball', 'volleyball'], true), 404, 'Sport bulunamadi');

        return $normalized;
    }

    private function validateDatasetPath(string $path, string $mustContain): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $real = realpath($normalized);
        abort_unless(is_string($real), 404, 'Dosya yolu bulunamadi');

        $datasetRoot = str_replace('\\', '/', realpath(base_path('ai-worker/datasets')) ?: '');
        $realNormalized = str_replace('\\', '/', $real);

        abort_unless($datasetRoot !== '' && str_starts_with($realNormalized, $datasetRoot), 403, 'Dosya yolu gecersiz');
        abort_unless(str_contains($realNormalized, $mustContain), 403, 'Beklenmeyen dosya tipi');

        return $realNormalized;
    }

    private function formatFloat(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
