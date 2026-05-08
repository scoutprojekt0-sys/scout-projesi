<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelRollout extends Model
{
    protected $fillable = [
        'sport',
        'from_model_version',
        'to_model_version',
        'action',
        'model_path',
        'ai_training_run_id',
        'notes',
        'rolled_out_at',
    ];

    protected $casts = [
        'rolled_out_at' => 'datetime',
    ];

    public function trainingRun(): BelongsTo
    {
        return $this->belongsTo(AiTrainingRun::class, 'ai_training_run_id');
    }
}
