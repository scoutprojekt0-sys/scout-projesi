<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'player_id',
        'club_id',
        'club_name',
        'contract_type',
        'start_date',
        'end_date',
        'salary',
        'currency',
        'status',
        'terms',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'salary' => 'decimal:2',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(User::class, 'club_id');
    }
}
