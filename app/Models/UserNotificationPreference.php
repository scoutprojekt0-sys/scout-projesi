<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'allow_match_alerts',
        'allow_push_notifications',
        'allow_inbox_push',
        'allow_offer_alerts',
        'sport',
        'city',
        'district',
    ];

    protected $casts = [
        'allow_match_alerts' => 'boolean',
        'allow_push_notifications' => 'boolean',
        'allow_inbox_push' => 'boolean',
        'allow_offer_alerts' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
