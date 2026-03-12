<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerSearch extends Model
{
    protected $fillable = [
        'manager_id',
        'position',
        'city',
        'min_age',
        'max_age',
        'min_height_cm',
        'max_height_cm',
        'min_rating',
        'save_search',
    ];

    protected $casts = [
        'save_search' => 'boolean',
        'min_rating' => 'decimal:2',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(PlayerSearchResult::class, 'search_id');
    }
}
