<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutPointLedger extends Model
{
    use HasFactory;

    protected $table = 'scout_point_ledger';

    protected $fillable = [
        'user_id',
        'scout_tip_id',
        'event_type',
        'points',
        'notes',
        'metadata',
    ];

    protected $casts = [
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
