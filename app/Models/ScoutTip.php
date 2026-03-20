<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutTip extends Model
{
    use HasFactory;

    protected $fillable = [
        'submitted_by',
        'player_id',
        'video_clip_id',
        'duplicate_of_tip_id',
        'status',
        'source_type',
        'player_name',
        'birth_year',
        'position',
        'foot',
        'height_cm',
        'city',
        'district',
        'neighborhood',
        'team_name',
        'competition_level',
        'match_date',
        'guardian_consent_status',
        'description',
        'ai_quality_score',
        'review_score',
        'final_score',
        'metadata',
        'screened_at',
        'shortlisted_at',
        'approved_at',
        'trial_at',
        'signed_at',
        'rewarded_at',
    ];

    protected $casts = [
        'birth_year' => 'integer',
        'height_cm' => 'integer',
        'match_date' => 'date',
        'ai_quality_score' => 'decimal:2',
        'review_score' => 'decimal:2',
        'final_score' => 'decimal:2',
        'metadata' => 'array',
        'screened_at' => 'datetime',
        'shortlisted_at' => 'datetime',
        'approved_at' => 'datetime',
        'trial_at' => 'datetime',
        'signed_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function videoClip()
    {
        return $this->belongsTo(VideoClip::class, 'video_clip_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(self::class, 'duplicate_of_tip_id');
    }

    public function events()
    {
        return $this->hasMany(ScoutTipEvent::class);
    }

    public function pointLedger()
    {
        return $this->hasMany(ScoutPointLedger::class);
    }

    public function rewards()
    {
        return $this->hasMany(ScoutReward::class);
    }
}
