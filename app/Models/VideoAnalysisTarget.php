<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAnalysisTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_analysis_id',
        'player_id',
        'label',
        'jersey_number',
        'reference_data',
    ];

    protected $casts = [
        'reference_data' => 'array',
    ];

    public function videoAnalysis()
    {
        return $this->belongsTo(VideoAnalysis::class);
    }

    public function player()
    {
        return $this->belongsTo(User::class);
    }
}
