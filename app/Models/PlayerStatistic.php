<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    protected $table = 'player_statistics';

    protected $fillable = [
        'user_id',
        'season',
        'club_id',
        'league',
        'matches_played',
        'matches_started',
        'matches_benched',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'minutes_played',
        'avg_rating',
        'metadata',
    ];

    protected $casts = [
        'matches_played'  => 'integer',
        'matches_started' => 'integer',
        'matches_benched' => 'integer',
        'goals'          => 'integer',
        'assists'        => 'integer',
        'yellow_cards'   => 'integer',
        'red_cards'      => 'integer',
        'minutes_played' => 'integer',
        'avg_rating'     => 'decimal:2',
        'metadata'       => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(User::class, 'club_id');
    }
}
