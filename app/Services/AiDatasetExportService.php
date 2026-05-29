<?php

namespace App\Services;

use App\Models\AiDatasetCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class AiDatasetExportService
{
    /**
     * @return array{exported:int,skipped:int,rows:array<int,array<string,mixed>>}
     */
    public function exportApprovedCandidates(?string $sport = null, ?int $limit = null): array
    {
        $query = AiDatasetCandidate::query()
            ->with('videoClip')
            ->where('status', AiDatasetCandidate::STATUS_APPROVED)
            ->orderBy('id');

        if ($sport !== null) {
            $query->where('sport', $sport);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $rows = [];
        $exported = 0;
        $skipped = 0;

        /** @var Collection<int, AiDatasetCandidate> $candidates */
        $candidates = $query->get();
        foreach ($candidates as $candidate) {
            $result = $this->exportOne($candidate);
            $rows[] = $result;
            if (($result['result'] ?? '') === 'exported') {
                $exported++;
            } else {
                $skipped++;
            }
        }

        return [
            'exported' => $exported,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function exportOne(AiDatasetCandidate $candidate): array
    {
        $clip = $candidate->videoClip;
        if ($clip === null) {
            return $this->skipResult($candidate, 'video_clip_missing');
        }

        $publicPath = $this->extractPublicDiskPath((string) $clip->video_url);
        if ($publicPath === null) {
            return $this->skipResult($candidate, 'video_url_not_local_public_storage');
        }

        if (! Storage::disk('public')->exists($publicPath)) {
            return $this->skipResult($candidate, 'public_video_file_missing');
        }

        $sourceAbsolutePath = Storage::disk('public')->path($publicPath);
        $extension = pathinfo($sourceAbsolutePath, PATHINFO_EXTENSION) ?: 'mp4';
        $targetDirectory = base_path('raw_videos/'.$candidate->sport);
        File::ensureDirectoryExists($targetDirectory);

        $sourceKey = $this->buildSourceKey($candidate, $clip->title);
        $targetFilename = $this->buildTargetFilename($candidate, $clip->title, $extension);
        $targetAbsolutePath = $targetDirectory.DIRECTORY_SEPARATOR.$targetFilename;

        if (! File::exists($targetAbsolutePath)) {
            File::copy($sourceAbsolutePath, $targetAbsolutePath);
        }

        $split = $candidate->split ?: $this->assignSplit($candidate->sport);
        $manifestPath = base_path('raw_videos/manifests/video_candidates_'.$candidate->sport.'.csv');
        $this->upsertManifestRow($manifestPath, [
            'candidate_id' => (string) $candidate->id,
            'video_clip_id' => (string) $candidate->video_clip_id,
            'user_id' => (string) $candidate->user_id,
            'sport' => $candidate->sport,
            'split' => $split,
            'status' => $candidate->status,
            'source_public_path' => $publicPath,
            'source_key' => $sourceKey,
            'raw_video_path' => str_replace('\\', '/', $targetAbsolutePath),
            'exported_at' => now()->toIso8601String(),
        ]);

        $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
        $metadata['export'] = [
            'source_key' => $sourceKey,
            'raw_video_path' => str_replace('\\', '/', $targetAbsolutePath),
            'manifest_path' => str_replace('\\', '/', $manifestPath),
            'source_public_path' => $publicPath,
            'exported_at' => now()->toIso8601String(),
        ];
        $metadata['source_key'] = $sourceKey;

        $candidate->forceFill([
            'split' => $split,
            'metadata' => $metadata,
        ])->save();

        return [
            'candidate_id' => $candidate->id,
            'video_clip_id' => $candidate->video_clip_id,
            'sport' => $candidate->sport,
            'split' => $split,
            'result' => 'exported',
            'raw_video_path' => str_replace('\\', '/', $targetAbsolutePath),
            'manifest_path' => str_replace('\\', '/', $manifestPath),
        ];
    }

    /**
     * @param  list<int>  $candidateIds
     */
    public function markCandidatesAsLabeling(array $candidateIds): int
    {
        if ($candidateIds === []) {
            return 0;
        }

        $updated = 0;
        AiDatasetCandidate::query()
            ->whereIn('id', $candidateIds)
            ->chunkById(100, function ($items) use (&$updated): void {
                foreach ($items as $candidate) {
                    $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
                    $metadata['labeling_queue'] = [
                        'status' => 'queued',
                        'queued_at' => now()->toIso8601String(),
                    ];

                    $candidate->forceFill([
                        'status' => $candidate->status === AiDatasetCandidate::STATUS_APPROVED
                            ? AiDatasetCandidate::STATUS_APPROVED
                            : AiDatasetCandidate::STATUS_LABELING,
                        'labeling_started_at' => $candidate->labeling_started_at ?? now(),
                        'metadata' => $metadata,
                    ])->save();

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @return array{updated:int,rows:array<int,array<string,mixed>>}
     */
    public function syncLabeledCandidates(string $sport): array
    {
        $manifestPath = base_path('ai-worker/datasets/'.$sport.'/manifest.csv');
        if (! File::exists($manifestPath)) {
            return ['updated' => 0, 'rows' => []];
        }

        $manifestRows = $this->readCsvRows($manifestPath);
        $rowsBySource = [];
        $rowsBySourceKey = [];
        foreach ($manifestRows as $row) {
            $sourceVideo = str_replace('\\', '/', trim((string) ($row['source_video'] ?? '')));
            if ($sourceVideo !== '') {
                $rowsBySource[$sourceVideo][] = $row;
                $rowsBySource[$this->normalizeWorkerPath($sourceVideo)][] = $row;
            }

            $sourceKey = trim((string) ($row['source_key'] ?? ''));
            if ($sourceKey !== '') {
                $rowsBySourceKey[$sourceKey][] = $row;
            }
        }

        $updated = 0;
        $results = [];

        $candidates = AiDatasetCandidate::query()
            ->where('sport', $sport)
            ->whereIn('status', [
                AiDatasetCandidate::STATUS_LABELING,
                AiDatasetCandidate::STATUS_APPROVED,
            ])
            ->get();

        foreach ($candidates as $candidate) {
            $rawVideoPath = str_replace('\\', '/', (string) ($candidate->metadata['export']['raw_video_path'] ?? ''));
            $sourceKey = (string) ($candidate->metadata['export']['source_key'] ?? $candidate->metadata['source_key'] ?? '');
            $labelRows = $rawVideoPath !== ''
                ? ($rowsBySource[$rawVideoPath] ?? $rowsBySource[$this->normalizeWorkerPath($rawVideoPath)] ?? null)
                : null;
            $labelRows ??= $sourceKey !== '' ? ($rowsBySourceKey[$sourceKey] ?? null) : null;

            if ($labelRows === null) {
                $results[] = [
                    'candidate_id' => $candidate->id,
                    'result' => 'skipped',
                    'reason' => 'manifest_rows_missing',
                ];
                continue;
            }

            $total = count($labelRows);
            $annotated = 0;
            foreach ($labelRows as $row) {
                $labelPath = $this->normalizeWorkerPath((string) ($row['labels_path'] ?? ''));
                if ($labelPath !== '' && File::exists($labelPath) && trim((string) File::get($labelPath)) !== '') {
                    $annotated++;
                }
            }

            $completion = $total > 0 ? round(($annotated / $total) * 100, 1) : 0.0;
            $minAnnotated = max(1, (int) env('AI_DATASET_CANDIDATE_MIN_ANNOTATED_FRAMES', 1));
            $minCompletion = max(0.0, (float) env('AI_DATASET_CANDIDATE_MIN_LABEL_COMPLETION_PERCENT', 0));

            if ($total > 0 && $annotated >= $minAnnotated && $completion >= $minCompletion) {
                $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
                $metadata['labeling_sync'] = [
                    'manifest_path' => str_replace('\\', '/', $manifestPath),
                    'annotated_frames' => $annotated,
                    'total_frames' => $total,
                    'label_completion_percent' => $completion,
                    'min_annotated_frames' => $minAnnotated,
                    'min_label_completion_percent' => $minCompletion,
                    'synced_at' => now()->toIso8601String(),
                ];

                $candidate->forceFill([
                    'status' => AiDatasetCandidate::STATUS_LABELED,
                    'labeled_at' => $candidate->labeled_at ?? now(),
                    'metadata' => $metadata,
                ])->save();
                $updated++;

                $results[] = [
                    'candidate_id' => $candidate->id,
                    'result' => 'labeled',
                    'annotated_frames' => $annotated,
                    'total_frames' => $total,
                    'completion' => $completion,
                ];
            } else {
                $results[] = [
                    'candidate_id' => $candidate->id,
                    'result' => 'pending',
                    'annotated_frames' => $annotated,
                    'total_frames' => $total,
                    'completion' => $completion,
                    'reason' => $annotated < $minAnnotated ? 'not_enough_annotated_frames' : 'completion_below_threshold',
                ];
            }
        }

        return ['updated' => $updated, 'rows' => $results];
    }

    private function normalizeWorkerPath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '/app/datasets/')) {
            return str_replace('\\', '/', base_path('ai-worker/datasets/'.substr($normalized, strlen('/app/datasets/'))));
        }

        if (str_starts_with($normalized, '/app/raw_videos/')) {
            return str_replace('\\', '/', base_path('raw_videos/'.substr($normalized, strlen('/app/raw_videos/'))));
        }

        return $normalized;
    }

    public function markSportCandidatesAsTrained(string $sport, string $modelVersion): int
    {
        $updated = 0;
        AiDatasetCandidate::query()
            ->where('sport', $sport)
            ->whereIn('status', [
                AiDatasetCandidate::STATUS_LABELED,
                AiDatasetCandidate::STATUS_APPROVED,
            ])
            ->chunkById(100, function ($items) use (&$updated, $modelVersion): void {
                foreach ($items as $candidate) {
                    $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
                    $metadata['training'] = [
                        'model_version' => $modelVersion,
                        'trained_at' => now()->toIso8601String(),
                    ];

                    $candidate->forceFill([
                        'status' => AiDatasetCandidate::STATUS_TRAINED,
                        'trained_at' => now(),
                        'model_version' => $modelVersion,
                        'metadata' => $metadata,
                    ])->save();
                    $updated++;
                }
            });

        return $updated;
    }

    private function skipResult(AiDatasetCandidate $candidate, string $reason): array
    {
        $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
        $metadata['export_skip_reason'] = $reason;
        $candidate->forceFill(['metadata' => $metadata])->save();

        return [
            'candidate_id' => $candidate->id,
            'video_clip_id' => $candidate->video_clip_id,
            'sport' => $candidate->sport,
            'result' => 'skipped',
            'reason' => $reason,
        ];
    }

    private function assignSplit(string $sport): string
    {
        $rows = AiDatasetCandidate::query()
            ->where('sport', $sport)
            ->whereNotNull('split')
            ->pluck('split');

        $counts = [
            'train' => 0,
            'val' => 0,
            'test' => 0,
        ];

        foreach ($rows as $split) {
            if (isset($counts[$split])) {
                $counts[$split]++;
            }
        }

        $total = array_sum($counts);
        if ($total < 10) {
            return 'train';
        }

        $ratios = [
            'train' => 0.70,
            'val' => 0.15,
            'test' => 0.15,
        ];

        $nextTotal = $total + 1;
        $bestSplit = 'train';
        $bestGap = null;

        foreach ($counts as $split => $count) {
            $projectedRatio = ($count + 1) / $nextTotal;
            $gap = abs($ratios[$split] - $projectedRatio);
            if ($bestGap === null || $gap < $bestGap) {
                $bestGap = $gap;
                $bestSplit = $split;
            }
        }

        return $bestSplit;
    }

    private function buildTargetFilename(AiDatasetCandidate $candidate, string $title, string $extension): string
    {
        return $this->buildSourceKey($candidate, $title).'.'.strtolower($extension);
    }

    private function buildSourceKey(AiDatasetCandidate $candidate, string $title): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $title);
        $slug = trim((string) $slug, '._-');
        if ($slug === '') {
            $slug = 'candidate_video';
        }

        return sprintf(
            'candidate_%d_clip_%d_%s',
            $candidate->id,
            $candidate->video_clip_id,
            strtolower($slug)
        );
    }

    private function upsertManifestRow(string $manifestPath, array $row): void
    {
        File::ensureDirectoryExists(dirname($manifestPath));
        $fieldNames = [
            'candidate_id',
            'video_clip_id',
            'user_id',
            'sport',
            'split',
            'status',
            'source_public_path',
            'source_key',
            'raw_video_path',
            'exported_at',
        ];

        $rows = [];
        if (File::exists($manifestPath)) {
            $handle = fopen($manifestPath, 'r');
            if (is_resource($handle)) {
                $header = fgetcsv($handle) ?: [];
                while (($data = fgetcsv($handle)) !== false) {
                    $rows[] = array_combine($header, $data);
                }
                fclose($handle);
            }
        }

        $updated = false;
        foreach ($rows as &$existingRow) {
            if ((string) ($existingRow['candidate_id'] ?? '') === (string) $row['candidate_id']) {
                $existingRow = $row;
                $updated = true;
                break;
            }
        }
        unset($existingRow);

        if (! $updated) {
            $rows[] = $row;
        }

        $handle = fopen($manifestPath, 'w');
        if (! is_resource($handle)) {
            throw new \RuntimeException('Manifest dosyasi yazilamadi: '.$manifestPath);
        }

        fputcsv($handle, $fieldNames);
        foreach ($rows as $manifestRow) {
            fputcsv($handle, array_map(static fn ($field) => $manifestRow[$field] ?? '', $fieldNames));
        }
        fclose($handle);
    }

    private function extractPublicDiskPath(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, '://') && ! str_starts_with($raw, '/')) {
            return ltrim($raw, '/');
        }

        $path = parse_url($raw, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/media-files/')) {
            return ltrim(substr($path, strlen('/media-files/')), '/');
        }

        if (str_starts_with($path, '/storage/')) {
            return ltrim(substr($path, strlen('/storage/')), '/');
        }

        return null;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function readCsvRows(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! is_resource($handle)) {
            return [];
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return [];
        }

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $data);
        }
        fclose($handle);

        return $rows;
    }
}
