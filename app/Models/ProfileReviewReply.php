<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReviewReply extends Model
{
    protected $fillable = [
        'review_id',
        'author_id',
        'body',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProfileReview::class, 'review_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
