<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReviewReport extends Model
{
    protected $fillable = [
        'review_id',
        'reported_by',
        'reason',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProfileReview::class, 'review_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
