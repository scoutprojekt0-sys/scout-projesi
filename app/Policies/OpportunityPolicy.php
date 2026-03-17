<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    /**
     * Determine if the user can view the opportunity.
     */
    public function view(?User $user, Opportunity $opportunity): bool
    {
        return $opportunity->status === 'active' || $opportunity->user_id === $user?->id;
    }

    /**
     * Determine if the user can create opportunities.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['team', 'manager'], true);
    }

    /**
     * Determine if the user can update the opportunity.
     */
    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }

    /**
     * Determine if the user can delete the opportunity.
     */
    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->id === $opportunity->user_id;
    }
}
