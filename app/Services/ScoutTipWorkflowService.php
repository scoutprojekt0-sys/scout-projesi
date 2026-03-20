<?php

namespace App\Services;

use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\ScoutReward;
use App\Models\ScoutTip;
use App\Models\ScoutTipEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScoutTipWorkflowService
{
    public function __construct(
        private readonly ScoutTipScoringService $scoringService,
        private readonly ScoutPointService $pointService,
    ) {
    }

    public function createTip(User $user, array $payload): ScoutTip
    {
        return DB::transaction(function () use ($user, $payload) {
            $duplicate = ScoutTip::query()
                ->whereRaw('lower(player_name) = ?', [mb_strtolower((string) $payload['player_name'])])
                ->when(! empty($payload['birth_year']), fn ($query) => $query->where('birth_year', $payload['birth_year']))
                ->where('city', $payload['city'])
                ->latest('id')
                ->first();

            $payload['ai_quality_score'] = $this->scoringService->calculateInitialScore($payload, $user);
            $payload['final_score'] = $payload['ai_quality_score'];
            $payload['duplicate_of_tip_id'] = $duplicate?->id;

            $tip = ScoutTip::create($payload + ['submitted_by' => $user->id]);

            $this->logEvent($tip, $user->id, 'tip_created', 'Scout tip submitted.', [
                'duplicate_detected' => $duplicate !== null,
            ]);

            $this->createModerationItem($tip, $user->id, $duplicate !== null);

            $user->increment('scout_tips_count');
            $this->pointService->award(
                $user->fresh(),
                $tip,
                'tip_submitted',
                $duplicate !== null ? 2 : 5,
                $duplicate !== null ? 'Potential duplicate scout tip submitted.' : 'Scout tip submitted.'
            );

            DataAuditLog::logChange(
                'ScoutTip',
                $tip->id,
                'created',
                null,
                $tip->toArray(),
                $user->id,
                'Crowdsourced scout tip submitted'
            );

            return $tip->fresh(['submitter', 'videoClip', 'duplicateOf']);
        });
    }

    public function updateStatus(ScoutTip $tip, User $actor, string $status, array $attributes = []): ScoutTip
    {
        return DB::transaction(function () use ($tip, $actor, $status, $attributes) {
            $oldValues = $tip->toArray();
            $eventType = 'tip_updated';
            $auditAction = 'updated';
            $points = 0;
            $eventNotes = $attributes['notes'] ?? null;

            switch ($status) {
                case 'screened':
                    $reviewScore = (float) ($attributes['review_score'] ?? $tip->ai_quality_score);
                    $tip->fill($this->scoringService->applyReviewScore($tip, $reviewScore));
                    $tip->status = 'screened';
                    $tip->screened_at = now();
                    $eventType = 'screened';
                    $points = $tip->duplicate_of_tip_id ? 0 : 10;
                    break;

                case 'shortlisted':
                    $tip->status = 'shortlisted';
                    $tip->shortlisted_at = now();
                    $eventType = 'shortlisted';
                    $points = 25;
                    break;

                case 'approved':
                    $tip->status = 'approved';
                    $tip->approved_at = now();
                    $eventType = 'approved';
                    $auditAction = 'verified';
                    $points = 15;
                    break;

                case 'rejected':
                    $tip->status = 'rejected';
                    $eventType = 'rejected';
                    $auditAction = 'rejected';
                    $points = -20;
                    break;

                case 'trial':
                    $tip->status = 'trial';
                    $tip->trial_at = now();
                    $eventType = 'trial_invite';
                    $points = 100;
                    $tip->submitter->increment('successful_tips_count');
                    break;

                case 'signed':
                    $tip->status = 'signed';
                    $tip->signed_at = now();
                    $eventType = 'signed';
                    $points = 500;
                    $tip->submitter->increment('successful_tips_count');
                    $this->createRewardCandidate($tip);
                    break;

                case 'withdrawn':
                    $tip->status = 'withdrawn';
                    $eventType = 'withdrawn';
                    break;
            }

            if (array_key_exists('player_id', $attributes)) {
                $tip->player_id = $attributes['player_id'];
            }

            $tip->save();
            $this->syncModeration($tip, $actor->id, $status, $eventNotes);
            $this->logEvent($tip, $actor->id, $eventType, $eventNotes, $attributes);

            if ($points !== 0) {
                $this->pointService->award($tip->submitter->fresh(), $tip, $eventType, $points, $eventNotes);
            } else {
                $this->pointService->refreshScoutProfile($tip->submitter->fresh());
            }

            DataAuditLog::logChange(
                'ScoutTip',
                $tip->id,
                $auditAction,
                $oldValues,
                $tip->fresh()->toArray(),
                $actor->id,
                $eventNotes
            );

            return $tip->fresh(['submitter', 'player', 'videoClip', 'events', 'rewards']);
        });
    }

    private function createModerationItem(ScoutTip $tip, int $submittedBy, bool $duplicateDetected): void
    {
        ModerationQueue::create([
            'model_type' => 'ScoutTip',
            'model_id' => $tip->id,
            'status' => 'pending',
            'priority' => $duplicateDetected ? 'high' : 'medium',
            'reason' => $duplicateDetected ? 'conflict_detected' : 'new_entry',
            'proposed_changes' => $tip->toArray(),
            'change_description' => $tip->description,
            'confidence_score' => $tip->ai_quality_score / 100,
            'submitted_by' => $submittedBy,
            'source_url' => $tip->videoClip?->video_url,
            'requires_dual_approval' => $duplicateDetected,
        ]);
    }

    private function syncModeration(ScoutTip $tip, int $reviewerId, string $status, ?string $notes): void
    {
        $moderation = ModerationQueue::query()
            ->where('model_type', 'ScoutTip')
            ->where('model_id', $tip->id)
            ->latest('id')
            ->first();

        if (! $moderation) {
            return;
        }

        $moderationStatus = match ($status) {
            'rejected' => 'rejected',
            'withdrawn' => 'flagged',
            default => 'approved',
        };

        $moderation->update([
            'status' => $moderationStatus,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes,
            'current_values' => $tip->toArray(),
        ]);
    }

    private function createRewardCandidate(ScoutTip $tip): void
    {
        ScoutReward::firstOrCreate(
            [
                'user_id' => $tip->submitted_by,
                'scout_tip_id' => $tip->id,
                'basis' => 'pro_contract',
            ],
            [
                'reward_type' => 'cash_bonus',
                'status' => 'pending',
                'amount' => 250,
                'currency' => 'EUR',
                'metadata' => ['source' => 'auto_generated_on_signing'],
            ]
        );
    }

    private function logEvent(ScoutTip $tip, ?int $actorId, string $eventType, ?string $notes = null, array $metadata = []): void
    {
        ScoutTipEvent::create([
            'scout_tip_id' => $tip->id,
            'actor_user_id' => $actorId,
            'event_type' => $eventType,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }
}
