<?php

namespace App\Policies;

use App\Models\ScoutTip;
use App\Models\User;

class ScoutTipPolicy
{
    public function view(User $user, ScoutTip $scoutTip): bool
    {
        return (int) $scoutTip->submitted_by === (int) $user->id || $this->canReview($user);
    }

    public function withdraw(User $user, ScoutTip $scoutTip): bool
    {
        return (int) $scoutTip->submitted_by === (int) $user->id
            && in_array($scoutTip->status, ['pending', 'screened'], true);
    }

    public function review(User $user): bool
    {
        return $this->canReview($user);
    }

    private function canReview(User $user): bool
    {
        return in_array((string) $user->editor_role, ['reviewer', 'senior_reviewer', 'admin'], true)
            || in_array((string) $user->role, ['scout', 'manager', 'coach', 'club', 'team', 'lawyer'], true);
    }
}
