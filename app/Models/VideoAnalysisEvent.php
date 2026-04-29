<?php

namespace App\Models;

use App\Casts\EncryptedJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAnalysisEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_analysis_id',
        'target_player_id',
        'event_type',
        'start_second',
        'end_second',
        'confidence',
        'payload',
    ];

    protected $casts = [
        'start_second' => 'integer',
        'end_second' => 'integer',
        'confidence' => 'decimal:2',
        'payload' => EncryptedJson::class,
    ];

    public function videoAnalysis()
    {
        return $this->belongsTo(VideoAnalysis::class);
    }

    public function targetPlayer()
    {
        return $this->belongsTo(User::class, 'target_player_id');
    }

    public function clips()
    {
        return $this->hasMany(VideoAnalysisClip::class);
    }
}
