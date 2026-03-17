<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveWatchRequest extends Model
{
    protected $fillable = [
        'requester_user_id',
        'requester_role',
        'target_date',
        'city',
        'district',
        'position',
        'radius_km',
        'notes',
        'status',
    ];

    protected $casts = [
        'target_date' => 'date',
        'radius_km' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }
}
