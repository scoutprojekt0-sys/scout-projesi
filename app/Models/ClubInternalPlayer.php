<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubInternalPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_user_id',
        'profile_type',
        'visibility',
        'group_key',
        'name',
        'gender',
        'sport',
        'birth_year',
        'age',
        'position',
        'height',
        'shirt_number',
        'contract_status',
        'contact',
        'dominant_foot',
        'bio',
        'note',
        'matches',
        'minutes',
        'goals',
        'assists',
        'rating',
    ];
}
