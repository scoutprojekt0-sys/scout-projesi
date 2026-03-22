<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmateurResult extends Model
{
    protected $fillable = [
        'league',
        'season',
        'country',
        'sport',
        'home_team',
        'away_team',
        'home_score',
        'away_score',
        'status',
        'source',
        'reviewed_at',
    ];

    protected $casts = [
        'home_score' => 'integer',
        'away_score' => 'integer',
        'reviewed_at' => 'datetime',
    ];
}
