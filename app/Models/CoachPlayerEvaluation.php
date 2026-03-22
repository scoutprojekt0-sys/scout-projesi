<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPlayerEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_user_id',
        'player_user_id',
        'player_name',
        'position',
        'decision_note',
        'scores',
        'average_score',
        'saved_label',
    ];

    protected $casts = [
        'scores' => 'array',
        'average_score' => 'decimal:1',
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
