<?php

namespace App\Models;

use App\Casts\EncryptedJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerVideoMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'video_analysis_id',
        'passes',
        'successful_passes',
        'cross_attempts',
        'successful_crosses',
        'shots',
        'dribbles',
        'ball_recoveries',
        'movement_score',
        'speed_score',
        'cross_quality_score',
        'assist_vision_score',
        'drive_efficiency_score',
        'spike_quality_score',
        'block_timing_score',
        'metadata',
    ];

    protected $casts = [
        'metadata' => EncryptedJson::class,
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function videoAnalysis()
    {
        return $this->belongsTo(VideoAnalysis::class);
    }
}
