<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function apply(User $user): bool
    {
        return $user->role === 'player';
    }

    public function viewIncoming(User $user): bool
    {
        return in_array($user->role, ['team', 'manager'], true);
    }

    public function viewOutgoing(User $user): bool
    {
        return $user->role === 'player';
    }

    public function changeStatus(User $user, Application $application): bool
    {
        return in_array($user->role, ['team', 'manager'], true)
            && (int) $application->opportunity->team_user_id === (int) $user->id;
    }
}
