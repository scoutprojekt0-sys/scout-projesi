<?php

namespace App\Services;

use App\Models\DataAuditLog;
use App\Models\ModerationQueue;
use App\Models\ScoutReward;
use App\Models\ScoutTip;
use App\Models\ScoutTipEvent;
use App\Models\User;
use App\Support\NotificationStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $resolvedPlayer = $this->resolvePlayerFromPayload($payload);
            if ($resolvedPlayer && empty($payload['player_id'])) {
                $payload['player_id'] = $resolvedPlayer->id;
                $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                    'resolved_player' => [
                        'id' => $resolvedPlayer->id,
                        'name' => $resolvedPlayer->name,
                        'matched_by' => 'player_name',
                    ],
                ]);
            }

            $duplicate = ScoutTip::query()
                ->whereRaw('lower(player_name) = ?', [mb_strtolower((string) $payload['player_name'])])
                ->when(! empty($payload['birth_year']), fn ($query) => $query->where('birth_year', $payload['birth_year']))
                ->where('city', $payload['city'])
                ->latest('id')
                ->first();
            $isGuestSubmission = (bool) data_get($payload, 'metadata.guest_submission', false);
            $isManagerSubmission = $user->role === 'manager';
            $isManagerInboxSubmission = in_array((string) $user->role, ['coach', 'team', 'club'], true);

            $payload['ai_quality_score'] = $this->scoringService->calculateInitialScore($payload, $user);
            $payload['final_score'] = $payload['ai_quality_score'];
            $payload['duplicate_of_tip_id'] = $duplicate?->id;
            if ($isManagerSubmission) {
                $payload['status'] = 'shortlisted';
                $payload['shortlisted_at'] = now();
                $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                    'manager_direct_shortlist' => true,
                    'submitted_role' => $user->role,
                ]);
            } elseif ($isManagerInboxSubmission) {
                $payload['status'] = 'shortlisted';
                $payload['shortlisted_at'] = now();
                $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                    'submitted_to_manager_shortlist' => true,
                    'submitted_role' => $user->role,
                ]);
            }

            $tip = ScoutTip::create($payload + ['submitted_by' => $user->id]);

            $this->logEvent($tip, $user->id, 'tip_created', 'Scout tip submitted.', [
                'duplicate_detected' => $duplicate !== null,
            ]);

            $this->createModerationItem($tip, $user->id, $duplicate !== null);

            if (! $isGuestSubmission && ! $isManagerSubmission && ! $isManagerInboxSubmission) {
                $user->increment('scout_tips_count');
                $this->pointService->award(
                    $user->fresh(),
                    $tip,
                    'tip_submitted',
                    $duplicate !== null ? 2 : 5,
                    $duplicate !== null ? 'Potential duplicate scout tip submitted.' : 'Scout tip submitted.'
                );
            }

            DataAuditLog::logChange(
                'ScoutTip',
                $tip->id,
                'created',
                null,
                $tip->toArray(),
                $user->id,
                'Crowdsourced scout tip submitted'
            );

            if ($isManagerInboxSubmission) {
                $this->notifyManagersAboutTip($tip, $user);
            } else {
                $this->notifyRelevantRolesAboutTip($tip, $user, $isManagerSubmission);
            }

            return $tip->fresh(['submitter', 'videoClip', 'duplicateOf']);
        });
    }

    public function updateStatus(ScoutTip $tip, User $actor, string $status, array $attributes = []): ScoutTip
    {
        return DB::transaction(function () use ($tip, $actor, $status, $attributes) {
            $oldValues = $tip->toArray();
            $isGuestSubmission = (bool) data_get($tip->metadata, 'guest_submission', false);
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
                    $points = $isGuestSubmission ? 0 : 100;
                    if (! $isGuestSubmission && $tip->submitter) {
                        $tip->submitter->increment('successful_tips_count');
                    }
                    break;

                case 'signed':
                    $tip->status = 'signed';
                    $tip->signed_at = now();
                    $eventType = 'signed';
                    $points = $isGuestSubmission ? 0 : 500;
                    if (! $isGuestSubmission && $tip->submitter) {
                        $tip->submitter->increment('successful_tips_count');
                    }
                    if (! $isGuestSubmission) {
                        $this->createRewardCandidate($tip);
                    }
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

            if (! $isGuestSubmission && $points !== 0 && $tip->submitter) {
                $this->pointService->award($tip->submitter->fresh(), $tip, $eventType, $points, $eventNotes);
            } elseif (! $isGuestSubmission && $tip->submitter) {
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

    private function notifyRelevantRolesAboutTip(ScoutTip $tip, User $submitter, bool $isManagerSubmission = false): void
    {
        $targetIds = User::query()
            ->whereIn('role', ['coach', 'team', 'club'])
            ->pluck('id');

        NotificationStore::sendToUsers($targetIds, $isManagerSubmission ? 'scout_tip_shortlisted' : 'scout_tip_created', [
            'scout_tip_id' => $tip->id,
            'player_name' => $tip->player_name,
            'position' => $tip->position,
            'city' => $tip->city,
            'status' => $tip->status,
            'submitted_by' => [
                'id' => $submitter->id,
                'name' => $submitter->name,
                'role' => $submitter->role,
            ],
        ]);
    }

    private function notifyManagersAboutTip(ScoutTip $tip, User $submitter): void
    {
        $targetIds = User::query()
            ->where('role', 'manager')
            ->pluck('id');

        NotificationStore::sendToUsers($targetIds, 'scout_tip_shortlisted', [
            'scout_tip_id' => $tip->id,
            'player_name' => $tip->player_name,
            'position' => $tip->position,
            'city' => $tip->city,
            'status' => $tip->status,
            'submitted_by' => [
                'id' => $submitter->id,
                'name' => $submitter->name,
                'role' => $submitter->role,
            ],
        ]);
    }

    private function resolvePlayerFromPayload(array $payload): ?User
    {
        $playerId = (int) ($payload['player_id'] ?? 0);
        if ($playerId > 0) {
            return User::query()
                ->where('role', 'player')
                ->find($playerId);
        }

        $playerName = trim((string) ($payload['player_name'] ?? ''));
        if ($playerName === '') {
            return null;
        }

        $matches = $this->findPlayerCandidates(
            name: $playerName,
            cityHint: (string) ($payload['city'] ?? ''),
            positionHint: (string) ($payload['position'] ?? '')
        );

        return $matches['best_match'];
    }

    public function findPlayerCandidates(string $name, ?string $cityHint = null, ?string $positionHint = null): array
    {
        $normalized = $this->normalizePlayerLookup($name);
        if ($normalized === '') {
            return [
                'best_match' => null,
                'exact_matches' => collect(),
                'candidates' => collect(),
            ];
        }

        $players = User::query()
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('role', 'player')
            ->get([
                'users.id',
                'users.name',
                'users.role',
                'users.city',
                DB::raw('COALESCE(player_profiles.position, users.position) as position'),
                'player_profiles.current_team as current_team',
                'player_profiles.birth_year as birth_year',
                'users.rating',
            ]);

        $ranked = $players
            ->map(function (User $player) use ($name, $cityHint, $positionHint) {
                return [
                    'player' => $player,
                    'score' => $this->playerMatchScore($player, $name, $cityHint, $positionHint),
                ];
            })
            ->filter(function (array $row) use ($normalized) {
                $playerNormalized = $this->normalizePlayerLookup((string) $row['player']->name);
                return $playerNormalized === $normalized
                    || str_contains($playerNormalized, $normalized)
                    || str_contains($normalized, $playerNormalized);
            })
            ->sortByDesc('score')
            ->values();

        $exactMatches = $ranked
            ->filter(fn (array $row) => $this->normalizePlayerLookup((string) $row['player']->name) === $normalized)
            ->values();

        $bestMatch = null;
        if ($exactMatches->count() === 1) {
            $bestMatch = $exactMatches->first()['player'];
        } elseif ($ranked->isNotEmpty()) {
            $bestMatch = $ranked->first()['player'];
        }

        return [
            'best_match' => $bestMatch,
            'exact_matches' => $exactMatches->pluck('player')->values(),
            'candidates' => $ranked->pluck('player')->values(),
        ];
    }

    private function normalizePlayerLookup(string $value): string
    {
        $normalized = Str::lower(preg_replace('/\s+/u', '', $value) ?? $value);
        return trim($normalized);
    }

    private function playerMatchScore(User $player, string $query, ?string $cityHint = null, ?string $positionHint = null): int
    {
        $normalizedQuery = $this->normalizePlayerLookup($query);
        $normalizedName = $this->normalizePlayerLookup((string) $player->name);
        $normalizedCity = $this->normalizePlayerLookup((string) ($player->city ?? ''));
        $normalizedPosition = $this->normalizePlayerLookup((string) ($player->position ?? ''));
        $normalizedTeam = $this->normalizePlayerLookup((string) ($player->current_team ?? ''));
        $normalizedCityHint = $this->normalizePlayerLookup((string) ($cityHint ?? ''));
        $normalizedPositionHint = $this->normalizePlayerLookup((string) ($positionHint ?? ''));

        if ($normalizedQuery === '') {
            return 0;
        }

        $score = 0;

        if ($normalizedName === $normalizedQuery) {
            $score += 1000;
        } elseif (str_starts_with($normalizedName, $normalizedQuery)) {
            $score += 700;
        } elseif (str_contains($normalizedName, $normalizedQuery)) {
            $score += 450;
        }

        $queryParts = preg_split('/\s+/u', trim(Str::lower($query))) ?: [];
        foreach ($queryParts as $part) {
            $normalizedPart = $this->normalizePlayerLookup((string) $part);
            if ($normalizedPart === '') {
                continue;
            }
            if ($normalizedName === $normalizedPart) {
                $score += 120;
            } elseif (str_starts_with($normalizedName, $normalizedPart)) {
                $score += 80;
            } elseif (str_contains($normalizedName, $normalizedPart)) {
                $score += 45;
            }
        }

        if ($normalizedCityHint !== '' && $normalizedCity === $normalizedCityHint) {
            $score += 90;
        }
        if ($normalizedPositionHint !== '' && $normalizedPosition === $normalizedPositionHint) {
            $score += 90;
        }
        if ($normalizedPositionHint !== '' && str_contains($normalizedTeam, $normalizedPositionHint)) {
            $score += 20;
        }

        $score += (int) round((float) ($player->rating ?? 0));

        return $score;
    }
}
