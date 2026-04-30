<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function clubUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'club_user_id');
    }
}
