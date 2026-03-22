<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPlayerNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_user_id',
        'player_user_id',
        'player_name',
        'position',
        'tag',
        'focus',
        'body',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }
}
