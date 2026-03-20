<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scout_tip_id',
        'reward_type',
        'status',
        'amount',
        'currency',
        'basis',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scoutTip()
    {
        return $this->belongsTo(ScoutTip::class);
    }
}
