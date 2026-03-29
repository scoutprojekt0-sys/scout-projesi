<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoutTipRoleRequest extends Model
{
    protected $fillable = [
        'scout_tip_id',
        'user_id',
        'role_type',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function scoutTip(): BelongsTo
    {
        return $this->belongsTo(ScoutTip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
