<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerCareerTimeline extends Model
{
    use HasFactory;

    protected $table = 'player_career_timeline';

    protected $fillable = [
        'player_id',
        'club_id',
        'start_date',
        'end_date',
        'season_start',
        'season_end',
        'is_current',
        'position',
        'contract_type',
        'appearances',
        'goals',
        'assists',
        'minutes_played',
        'yellow_cards',
        'red_cards',
        'source_url',
        'confidence_score',
        'verification_status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'appearances' => 'integer',
        'goals' => 'integer',
        'assists' => 'integer',
        'minutes_played' => 'integer',
        'yellow_cards' => 'integer',
        'red_cards' => 'integer',
        'confidence_score' => 'decimal:2',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function club()
    {
        return $this->belongsTo(User::class, 'club_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function getDurationInDaysAttribute(): int
    {
        $end = $this->end_date ?? now();
        return $this->start_date->diffInDays($end);
    }

    public function getGoalsPerAppearanceAttribute(): float
    {
        if ($this->appearances === 0) {
            return 0.0;
        }

        return round($this->goals / $this->appearances, 2);
    }

    public function getMinutesPerAppearanceAttribute(): float
    {
        if ($this->appearances === 0) {
            return 0.0;
        }

        return round($this->minutes_played / $this->appearances, 0);
    }
}
