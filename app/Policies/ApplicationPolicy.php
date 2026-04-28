<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function apply(User $user): bool
    {
        return in_array($user->role, ['player', 'team', 'club', 'scout', 'manager', 'coach'], true);
    }

    public function viewIncoming(User $user): bool
    {
        return in_array($user->role, ['team', 'club', 'manager', 'coach', 'scout'], true);
    }

    public function viewOutgoing(User $user): bool
    {
        return in_array($user->role, ['player', 'team', 'club', 'scout', 'manager', 'coach'], true);
    }

    public function changeStatus(User $user, Application $application): bool
    {
        return in_array($user->role, ['team', 'club', 'manager', 'coach', 'scout'], true)
            && (int) $application->opportunity->team_user_id === (int) $user->id;
    }
}
