<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_user_id',
        'transfer_id',
        'target_player_user_id',
        'player_name',
        'offer_type',
        'amount_eur',
        'currency',
        'season',
        'contract_years',
        'salary_amount',
        'signing_fee',
        'bonus_summary',
        'contract_start_date',
        'contract_end_date',
        'expires_at',
        'clauses',
        'status',
        'note',
    ];

    protected $casts = [
        'amount_eur' => 'decimal:2',
        'contract_years' => 'integer',
        'salary_amount' => 'decimal:2',
        'signing_fee' => 'decimal:2',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'expires_at' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(PlayerTransfer::class, 'transfer_id');
    }

    public function club()
    {
        return $this->belongsTo(User::class, 'club_user_id');
    }
}
