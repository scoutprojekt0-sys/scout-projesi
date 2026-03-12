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
        return $user->role === 'team';
    }

    public function viewOutgoing(User $user): bool
    {
        return $user->role === 'player';
    }

    public function changeStatus(User $user, Application $application): bool
    {
        return $user->role === 'team'
            && (int) $application->opportunity->team_user_id === (int) $user->id;
    }
}
