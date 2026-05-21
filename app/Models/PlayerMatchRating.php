<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMatchRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_match_id',
        'club_user_id',
        'club_internal_player_id',
        'sport',
        'position',
        'minutes_played',
        'base_score',
        'positive_score',
        'negative_score',
        'final_rating',
        'summary_json',
    ];

    protected $casts = [
        'minutes_played' => 'integer',
        'base_score' => 'decimal:2',
        'positive_score' => 'decimal:2',
        'negative_score' => 'decimal:2',
        'final_rating' => 'decimal:2',
        'summary_json' => 'array',
    ];
}
