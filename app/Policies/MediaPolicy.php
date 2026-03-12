<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function delete(User $user, Media $media): bool
    {
        return (int) $media->user_id === (int) $user->id;
    }
}
