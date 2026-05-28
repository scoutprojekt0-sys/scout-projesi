<?php

namespace App\Services;

use App\Models\AiDatasetCandidate;
use App\Models\VideoAnalysis;
use Illuminate\Support\Facades\File;

class AiPseudoLabelService
{
    /**
     * @return array{updated:int,rows:array<int,array<string,mixed>>}
     */
    public function writeLabelsForSport(string $sport, ?int $limit = null): array
    {
        $sport = strtolower(trim($sport));
        $manifestPath = base_path('ai-worker/datasets/'.$sport.'/manifest.csv');
        if (! File::exists($manifestPath)) {
            return ['updated' => 0, 'rows' => []];
        }

        $manifestRows = $this->readCsvRows($manifestPath);
        $rowsBySourceKey = [];
        foreach ($manifestRows as $row) {
            $sourceKey = trim((string) ($row['source_key'] ?? ''));
            if ($sourceKey !== '') {
                $rowsBySourceKey[$sourceKey][] = $row;
            }
        }

        $query = AiDatasetCandidate::query()
            ->with('videoClip')
            ->where('sport', $sport)
            ->whereIn('status', [
                AiDatasetCandidate::STATUS_QUEUED,
                AiDatasetCandidate::STATUS_LABELING,
                AiDatasetCandidate::STATUS_LABELED,
                AiDatasetCandidate::STATUS_APPROVED,
                AiDatasetCandidate::STATUS_TRAINED,
            ])
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $updated = 0;
        $results = [];
        foreach ($query->get() as $candidate) {
            $sourceKey = (string) ($candidate->metadata['export']['source_key'] ?? $candidate->metadata['source_key'] ?? '');
            $manifestForCandidate = $sourceKey !== '' ? ($rowsBySourceKey[$sourceKey] ?? []) : [];
            if ($manifestForCandidate === []) {
                $results[] = $this->result($candidate, 'skipped', 'manifest_rows_missing');
                continue;
            }

            $analysis = VideoAnalysis::query()
                ->with('events')
                ->where('video_clip_id', $candidate->video_clip_id)
                ->where('target_player_id', $candidate->user_id)
                ->where('status', 'completed')
                ->latest('id')
                ->first();

            if (! $analysis) {
                $results[] = $this->result($candidate, 'skipped', 'completed_analysis_missing');
                continue;
            }

            $events = $analysis->events
                ->filter(fn ($event): bool => $this->normalizedConfidence($event->confidence) >= $this->minConfidence())
                ->values();

            if ($events->isEmpty()) {
                $results[] = $this->result($candidate, 'skipped', 'high_confidence_events_missing');
                continue;
            }

            $written = 0;
            $total = 0;
            foreach ($manifestForCandidate as $row) {
                $labelPath = $this->normalizeWorkerPath((string) ($row['labels_path'] ?? ''));
                if ($labelPath === '') {
                    continue;
                }

                $total++;
                $frameSecond = $this->frameSecond((string) ($row['frame_path'] ?? ''));
                if ($frameSecond === null || ! $this->hasEventAtSecond($events, $frameSecond)) {
                    continue;
                }

                File::ensureDirectoryExists(dirname($labelPath));
                if (File::exists($labelPath) && trim((string) File::get($labelPath)) !== '') {
                    continue;
                }

                File::put($labelPath, $this->playerPseudoLabel());
                $written++;
            }

            $metadata = is_array($candidate->metadata) ? $candidate->metadata : [];
            $metadata['pseudo_label_policy'] = [
                'source' => 'video_analysis_events',
                'analysis_id' => $analysis->id,
                'min_confidence' => $this->minConfidence(),
                'written_labels' => $written,
                'manifest_frames' => $total,
                'requires_manual_review' => true,
                'updated_at' => now()->toIso8601String(),
            ];
            $candidate->forceFill(['metadata' => $metadata])->save();

            if ($written > 0) {
                $updated++;
            }

            $results[] = [
                'candidate_id' => $candidate->id,
                'result' => $written > 0 ? 'labeled' : 'pending',
                'written_labels' => $written,
                'total_frames' => $total,
                'reason' => $written > 0 ? null : 'no_matching_event_frames',
            ];
        }

        return ['updated' => $updated, 'rows' => $results];
    }

    private function minConfidence(): float
    {
        return (float) config('scout.ai_training.pseudo_label.min_confidence', 0.65);
    }

    private function normalizedConfidence(mixed $confidence): float
    {
        $value = (float) $confidence;

        return $value > 1 ? $value / 100 : $value;
    }

    private function hasEventAtSecond($events, int $second): bool
    {
        foreach ($events as $event) {
            if ($second >= (int) $event->start_second && $second <= max((int) $event->start_second, (int) $event->end_second)) {
                return true;
            }
        }

        return false;
    }

    private function frameSecond(string $framePath): ?int
    {
        $filename = pathinfo(str_replace('\\', '/', $framePath), PATHINFO_FILENAME);
        if (preg_match('/_s(\d+)(?:_\d+)?$/', $filename, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function playerPseudoLabel(): string
    {
        return "0 0.500000 0.500000 0.350000 0.550000\n";
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

        return $normalized;
    }

    /**
     * @return list<array<string,string>>
     */
    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, array_pad($row, count($headers), '')) ?: [];
        }
        fclose($handle);

        return $rows;
    }

    private function result(AiDatasetCandidate $candidate, string $result, string $reason): array
    {
        return [
            'candidate_id' => $candidate->id,
            'result' => $result,
            'written_labels' => 0,
            'total_frames' => 0,
            'reason' => $reason,
        ];
    }
}
