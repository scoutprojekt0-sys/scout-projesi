<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_user_id',
        'target_player_user_id',
        'player_name',
        'amount_eur',
        'status',
        'note',
    ];

    protected $casts = [
        'amount_eur' => 'decimal:2',
    ];
}
