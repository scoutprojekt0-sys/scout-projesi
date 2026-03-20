<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoutTipEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'scout_tip_id',
        'actor_user_id',
        'event_type',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function scoutTip()
    {
        return $this->belongsTo(ScoutTip::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
