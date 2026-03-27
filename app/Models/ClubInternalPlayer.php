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
        'status',
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
        'coach_note',
        'manager_note',
        'note',
        'note_history',
        'matches',
        'minutes',
        'goals',
        'assists',
        'rating',
        'performance_history',
        'timeline_events',
    ];

    protected $casts = [
        'note_history' => 'array',
        'performance_history' => 'array',
        'timeline_events' => 'array',
    ];
}
