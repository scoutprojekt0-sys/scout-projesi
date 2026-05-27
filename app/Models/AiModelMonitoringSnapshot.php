<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModelMonitoringSnapshot extends Model
{
    protected $fillable = [
        'sport',
        'model_version',
        'sample_count',
        'window_started_at',
        'window_ended_at',
        'metric_summary',
        'drift_summary',
        'drift_detected',
        'auto_rollback_executed',
        'rollback_target_model_version',
        'captured_at',
    ];

    protected $casts = [
        'sample_count' => 'integer',
        'metric_summary' => 'array',
        'drift_summary' => 'array',
        'drift_detected' => 'boolean',
        'auto_rollback_executed' => 'boolean',
        'window_started_at' => 'datetime',
        'window_ended_at' => 'datetime',
        'captured_at' => 'datetime',
    ];
}
