<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAnalysisClip extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_analysis_event_id',
        'clip_url',
        'thumbnail_url',
        'clip_start_second',
        'clip_end_second',
        'metadata',
    ];

    protected $casts = [
        'clip_start_second' => 'integer',
        'clip_end_second' => 'integer',
        'metadata' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(VideoAnalysisEvent::class, 'video_analysis_event_id');
    }
}
