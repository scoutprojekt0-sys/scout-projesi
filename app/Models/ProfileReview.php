<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProfileReview extends Model
{
    protected $fillable = [
        'author_id',
        'author_role',
        'target_id',
        'target_role',
        'relationship_type',
        'sentiment',
        'body',
        'status',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(ProfileReviewReply::class, 'review_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProfileReviewReport::class, 'review_id');
    }
}
