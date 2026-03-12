<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lawyer extends Model
{
    protected $fillable = [
        'user_id',
        'license_number',
        'specialization',
        'bio',
        'office_name',
        'office_address',
        'office_phone',
        'office_email',
        'years_experience',
        'hourly_rate',
        'contract_fee',
        'is_verified',
        'is_active',
        'license_status',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'contract_fee' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
