<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutPlayerReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'scout_user_id',
        'player_user_id',
        'player_name',
        'position',
        'age',
        'rating',
        'status',
        'scout_name',
        'club',
        'watched_at',
        'potential',
        'summary',
        'strengths',
        'risks',
        'note',
    ];

    protected $casts = [
        'watched_at' => 'date',
        'strengths' => 'array',
        'risks' => 'array',
        'rating' => 'decimal:1',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }
}
