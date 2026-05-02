<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubTeamGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_user_id',
        'group_key',
        'name',
        'note',
        'is_showcased',
        'sort_order',
    ];

    protected $casts = [
        'is_showcased' => 'boolean',
        'sort_order' => 'integer',
    ];
}
