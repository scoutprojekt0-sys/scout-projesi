<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawyerWorkspaceItem extends Model
{
    protected $fillable = [
        'user_id',
        'item_type',
        'title',
        'counterparty',
        'fee_label',
        'priority',
        'status',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
