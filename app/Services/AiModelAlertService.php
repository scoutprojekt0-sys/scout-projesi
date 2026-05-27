<?php

namespace App\Services;

use App\Models\AiModelMonitoringSnapshot;
use App\Models\User;
use App\Support\NotificationStore;
use Illuminate\Support\Facades\Log;

class AiModelAlertService
{
    public function notifyDriftDetected(AiModelMonitoringSnapshot $snapshot): void
    {
        $payload = $this->buildMonitoringPayload(
            snapshot: $snapshot,
            notificationType: 'ai_model_drift_detected',
            targetModelVersion: null
        );

        Log::warning('AI model drift detected', [
            'sport' => $snapshot->sport,
            'model_version' => $snapshot->model_version,
            'sample_count' => $snapshot->sample_count,
            'captured_at' => optional($snapshot->captured_at)?->toIso8601String(),
            'drift_summary' => $snapshot->drift_summary,
        ]);

        $recipientIds = $this->resolveRecipientIds();
        if ($recipientIds === []) {
            return;
        }

        NotificationStore::sendToUsers(
            $recipientIds,
            'ai_model_drift_detected',
            $payload,
            'AI model drift tespit edildi',
            sprintf(
                '%s modeli icin runtime drift sinyali algilandi. Monitoring panelini incele.',
                ucfirst($snapshot->sport)
            ),
            'medium'
        );
    }

    public function notifyRollbackExecuted(AiModelMonitoringSnapshot $snapshot, string $targetModelVersion): void
    {
        $payload = $this->buildMonitoringPayload(
            snapshot: $snapshot,
            notificationType: 'ai_model_auto_rollback',
            targetModelVersion: $targetModelVersion
        );

        Log::critical('AI model auto rollback executed', [
            'sport' => $snapshot->sport,
            'from_model_version' => $snapshot->model_version,
            'to_model_version' => $targetModelVersion,
            'sample_count' => $snapshot->sample_count,
            'captured_at' => optional($snapshot->captured_at)?->toIso8601String(),
            'drift_summary' => $snapshot->drift_summary,
        ]);

        $recipientIds = $this->resolveRecipientIds();
        if ($recipientIds === []) {
            return;
        }

        NotificationStore::sendToUsers(
            $recipientIds,
            'ai_model_auto_rollback',
            $payload,
            'AI model rollback tetiklendi',
            sprintf(
                '%s modeli drift nedeniyle %s surumunden %s surumune geri alindi.',
                ucfirst($snapshot->sport),
                $snapshot->model_version,
                $targetModelVersion
            ),
            'high'
        );
    }

    private function resolveRecipientIds(): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'scout'])
            ->pluck('id')
            ->all();
    }

    private function buildMonitoringPayload(
        AiModelMonitoringSnapshot $snapshot,
        string $notificationType,
        ?string $targetModelVersion,
    ): array {
        return [
            'type' => $notificationType,
            'category' => 'ai_model_monitoring',
            'route' => '/ai-model-monitoring',
            'screen' => 'ai_model_monitoring',
            'target' => 'ai_model_monitoring',
            'action' => $notificationType === 'ai_model_auto_rollback' ? 'open_monitoring_rollback' : 'open_monitoring_drift',
            'sport' => $snapshot->sport,
            'model_sport' => $snapshot->sport,
            'model_version' => $snapshot->model_version,
            'from_model_version' => $snapshot->model_version,
            'rollback_target_model_version' => $targetModelVersion,
            'to_model_version' => $targetModelVersion,
            'sample_count' => $snapshot->sample_count,
            'captured_at' => optional($snapshot->captured_at)?->toIso8601String(),
            'drift_detected' => (bool) $snapshot->drift_detected,
            'auto_rollback_executed' => (bool) $snapshot->auto_rollback_executed,
        ];
    }
}
