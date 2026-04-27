<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'player_id',
        'from_club_id',
        'from_club_name',
        'to_club_id',
        'to_club_name',
        'fee',
        'counter_fee',
        'currency',
        'transfer_date',
        'transfer_type',
        'contract_until',
        'season',
        'window',
        'source_url',
        'confidence_score',
        'verification_status',
        'notes',
        'negotiation_status',
        'negotiation_notes',
        'created_by',
        'verified_by',
        'negotiation_updated_by',
        'verified_at',
        'negotiation_updated_at',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'counter_fee' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'transfer_date' => 'date',
        'contract_until' => 'date',
        'verified_at' => 'datetime',
        'negotiation_updated_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function fromClub()
    {
        return $this->belongsTo(User::class, 'from_club_id');
    }

    public function toClub()
    {
        return $this->belongsTo(User::class, 'to_club_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function negotiationUpdater()
    {
        return $this->belongsTo(User::class, 'negotiation_updated_by');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeBySeason($query, string $season)
    {
        return $query->where('season', $season);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function hasSource(): bool
    {
        return !empty($this->source_url);
    }

    public function getFormattedFeeAttribute(): string
    {
        if (is_null($this->fee)) {
            return $this->transfer_type === 'free' ? 'Free Transfer' : 'Unknown';
        }

        return number_format($this->fee, 0, ',', '.') . ' ' . $this->currency;
    }
}
