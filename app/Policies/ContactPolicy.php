<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function create(User $user, int $toUserId): bool
    {
        return (int) $user->id !== $toUserId;
    }

    public function viewInbox(User $user): bool
    {
        return $user->id > 0;
    }

    public function viewSent(User $user): bool
    {
        return $user->id > 0;
    }

    public function changeStatus(User $user, Contact $contact): bool
    {
        return (int) $contact->to_user_id === (int) $user->id;
    }
}
