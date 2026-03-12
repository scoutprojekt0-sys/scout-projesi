<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSearchResult extends Model
{
    protected $fillable = [
        'search_id',
        'player_id',
        'match_score',
        'match_details',
    ];

    protected $casts = [
        'match_score' => 'decimal:2',
        'match_details' => 'array',
    ];

    public function search(): BelongsTo
    {
        return $this->belongsTo(PlayerSearch::class, 'search_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }
}
