<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'boost_package_id',
        'amount',
        'currency',
        'payment_method',
        'payment_context',
        'transaction_id',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function boostPackage(): BelongsTo
    {
        return $this->belongsTo(BoostPackage::class);
    }

    public function playerBoost(): HasOne
    {
        return $this->hasOne(PlayerBoost::class);
    }
}
