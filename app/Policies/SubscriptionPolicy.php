<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * Determine if the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine if the user can create subscriptions.
     */
    public function create(User $user): bool
    {
        // Check if user already has an active subscription
        return !$user->subscriptions()->where('status', 'active')->exists();
    }

    /**
     * Determine if the user can cancel the subscription.
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id && $subscription->status === 'active';
    }
}
