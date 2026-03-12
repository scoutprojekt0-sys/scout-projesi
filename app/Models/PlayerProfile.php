<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProfile extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'birth_year',
        'position',
        'dominant_foot',
        'height_cm',
        'weight_kg',
        'bio',
        'current_team',
    ];

    protected $casts = [
        'user_id'     => 'integer',
        'birth_year'  => 'integer',
        'height_cm'   => 'integer',
        'weight_kg'   => 'integer',
        'updated_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_year) {
            return null;
        }

        return (int) now()->format('Y') - $this->birth_year;
    }
}
