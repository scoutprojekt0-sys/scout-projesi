<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchSchedule extends Model
{
    protected $fillable = [
        'player_user_id',
        'match_title',
        'team_name',
        'opponent_name',
        'position',
        'match_date',
        'city',
        'district',
        'venue',
        'latitude',
        'longitude',
        'is_public',
        'notes',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_public' => 'boolean',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }
}
