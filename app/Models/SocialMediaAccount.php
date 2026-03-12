<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialMediaAccount extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'username',
        'url',
        'follower_count',
        'verified',
        'metadata',
    ];

    protected $casts = [
        'verified'      => 'boolean',
        'follower_count' => 'integer',
        'metadata'      => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
