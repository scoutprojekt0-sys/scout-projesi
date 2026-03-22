<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubPromo extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_user_id',
        'club_name',
        'notes',
        'video_url',
        'images',
        'paid',
    ];

    protected $casts = [
        'images' => 'array',
        'paid' => 'boolean',
    ];
}
