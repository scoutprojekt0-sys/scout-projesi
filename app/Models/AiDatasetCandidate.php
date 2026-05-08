<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiDatasetCandidate extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_LABELING = 'labeling';
    public const STATUS_LABELED = 'labeled';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_TRAINED = 'trained';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_LABELING,
        self::STATUS_LABELED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_TRAINED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'video_clip_id',
        'user_id',
        'sport',
        'status',
        'split',
        'source_type',
        'notes',
        'metadata',
        'queued_at',
        'labeling_started_at',
        'labeled_at',
        'reviewed_at',
        'trained_at',
        'reviewed_by',
        'model_version',
    ];

    protected $casts = [
        'metadata' => 'array',
        'queued_at' => 'datetime',
        'labeling_started_at' => 'datetime',
        'labeled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'trained_at' => 'datetime',
    ];

    public function videoClip(): BelongsTo
    {
        return $this->belongsTo(VideoClip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function trainingRuns(): BelongsToMany
    {
        return $this->belongsToMany(AiTrainingRun::class, 'ai_dataset_candidate_training_run');
    }
}
