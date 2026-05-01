<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveMatch extends Model
{
    protected $fillable = [
        'title',
        'league',
        'home_team',
        'away_team',
        'home_score',
        'away_score',
        'match_date',
        'is_live',
        'is_finished',
        'visibility',
        'club_user_id',
        'group_key',
        'periods',
        'started_at',
        'finished_at',
        'round',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'is_live' => 'boolean',
        'is_finished' => 'boolean',
        'periods' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
