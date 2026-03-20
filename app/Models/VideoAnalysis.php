<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_clip_id',
        'requested_by',
        'target_player_id',
        'status',
        'analysis_type',
        'analysis_version',
        'summary',
        'raw_output',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'raw_output' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function videoClip()
    {
        return $this->belongsTo(VideoClip::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function targetPlayer()
    {
        return $this->belongsTo(User::class, 'target_player_id');
    }

    public function events()
    {
        return $this->hasMany(VideoAnalysisEvent::class);
    }

    public function clips()
    {
        return $this->hasManyThrough(VideoAnalysisClip::class, VideoAnalysisEvent::class);
    }

    public function metrics()
    {
        return $this->hasMany(PlayerVideoMetric::class);
    }

    public function targets()
    {
        return $this->hasMany(VideoAnalysisTarget::class);
    }
}
