<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'payload',
        'priority',
        'is_read',
        'read_at',
        'related_player_id',
        'related_match_schedule_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_player_id');
    }

    public function relatedMatchSchedule(): BelongsTo
    {
        return $this->belongsTo(PlayerMatchSchedule::class, 'related_match_schedule_id');
    }
}
