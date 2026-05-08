<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActiveModel extends Model
{
    protected $fillable = [
        'sport',
        'model_version',
        'model_path',
        'ai_training_run_id',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function trainingRun(): BelongsTo
    {
        return $this->belongsTo(AiTrainingRun::class, 'ai_training_run_id');
    }
}
