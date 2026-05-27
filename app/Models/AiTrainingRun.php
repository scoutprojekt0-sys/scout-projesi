<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiTrainingRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sport',
        'status',
        'model_version',
        'device',
        'epochs',
        'imgsz',
        'batch',
        'forced',
        'candidate_count',
        'candidate_ids',
        'notes',
        'output_log',
        'validation_summary',
        'validation_passed',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'forced' => 'boolean',
        'candidate_ids' => 'array',
        'validation_summary' => 'array',
        'validation_passed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(AiDatasetCandidate::class, 'ai_dataset_candidate_training_run');
    }
}
