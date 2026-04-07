<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPlayerClip extends Model
{
    use HasFactory;

    protected $table = 'coach_player_clips';

    protected $fillable = [
        'coach_user_id',
        'player_user_id',
        'player_name',
        'video_clip_id',
        'video_analysis_id',
        'video_url',
        'minute_mark',
        'second_mark',
        'start_second',
        'end_second',
        'stamp',
        'range_label',
        'shared_roles',
        'analysis_summary',
        'body',
    ];

    protected $casts = [
        'video_clip_id' => 'integer',
        'video_analysis_id' => 'integer',
        'minute_mark' => 'integer',
        'second_mark' => 'integer',
        'start_second' => 'integer',
        'end_second' => 'integer',
        'shared_roles' => 'array',
        'analysis_summary' => 'array',
    ];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(VideoClip::class, 'video_clip_id');
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(VideoAnalysis::class, 'video_analysis_id');
    }

    public function getAuthorUserIdAttribute(): ?int
    {
        return $this->coach_user_id ? (int) $this->coach_user_id : null;
    }
}
