<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveMatchEvent extends Model
{
    protected $fillable = [
        'live_match_id',
        'club_user_id',
        'club_internal_player_id',
        'group_key',
        'event_type',
        'period',
        'minute',
        'second',
        'x',
        'y',
        'created_by_user_id',
    ];

    protected $casts = [
        'period' => 'integer',
        'minute' => 'integer',
        'second' => 'integer',
        'x' => 'float',
        'y' => 'float',
    ];
}
