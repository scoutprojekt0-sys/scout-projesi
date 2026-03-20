<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoClip extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'platform',
        'platform_video_id',
        'duration_seconds',
        'match_date',
        'tags',
        'metadata',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'match_date'       => 'date',
        'tags'            => 'array',
        'metadata'        => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scoutTips()
    {
        return $this->hasMany(ScoutTip::class, 'video_clip_id');
    }

    public function analyses()
    {
        return $this->hasMany(VideoAnalysis::class);
    }
}
