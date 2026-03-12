<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMarketValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'value',
        'currency',
        'valuation_date',
        'calculation_factors',
        'explanation',
        'previous_value',
        'value_change',
        'value_change_percent',
        'peak_value',
        'peak_value_date',
        'source_url',
        'confidence_score',
        'verification_status',
        'model_version',
        'created_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'previous_value' => 'decimal:2',
        'value_change' => 'decimal:2',
        'value_change_percent' => 'decimal:2',
        'peak_value' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'calculation_factors' => 'array',
        'valuation_date' => 'date',
        'peak_value_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('valuation_date', 'desc');
    }

    public function getFormattedValueAttribute(): string
    {
        return number_format($this->value, 0, ',', '.') . ' ' . $this->currency;
    }

    public function getValueTrendAttribute(): string
    {
        if (is_null($this->value_change)) {
            return 'stable';
        }

        if ($this->value_change > 0) {
            return 'increasing';
        }

        if ($this->value_change < 0) {
            return 'decreasing';
        }

        return 'stable';
    }

    public function isAtPeak(): bool
    {
        return $this->value >= $this->peak_value;
    }

    /**
     * Calculate market value based on factors (v1 simple model)
     */
    public static function calculateValue(User $player): array
    {
        $age = $player->age ?? 25;
        $position = $player->position ?? 'MF';

        // Base value by age
        $baseValue = 100000; // 100k EUR base

        if ($age >= 18 && $age <= 23) {
            $ageMultiplier = 2.5; // Young talent premium
        } elseif ($age >= 24 && $age <= 28) {
            $ageMultiplier = 3.0; // Peak years
        } elseif ($age >= 29 && $age <= 32) {
            $ageMultiplier = 2.0; // Experienced
        } else {
            $ageMultiplier = 1.0; // Youth or veteran
        }

        // Position multiplier (attackers worth more)
        $positionMultipliers = [
            'GK' => 1.2,
            'DF' => 1.3,
            'MF' => 1.5,
            'FW' => 2.0,
        ];
        $positionMultiplier = $positionMultipliers[$position] ?? 1.5;

        $calculatedValue = $baseValue * $ageMultiplier * $positionMultiplier;

        return [
            'value' => round($calculatedValue, 2),
            'factors' => [
                'age' => $age,
                'age_multiplier' => $ageMultiplier,
                'position' => $position,
                'position_multiplier' => $positionMultiplier,
                'base_value' => $baseValue,
            ],
            'explanation' => sprintf(
                'Value calculated based on age (%d, multiplier: %.1fx) and position (%s, multiplier: %.1fx). Base value: %s EUR.',
                $age,
                $ageMultiplier,
                $position,
                $positionMultiplier,
                number_format($baseValue, 0, ',', '.')
            ),
        ];
    }
}
