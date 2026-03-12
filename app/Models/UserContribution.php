<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'contribution_type',
        'proposed_data',
        'current_data',
        'description',
        'source_url',
        'proof_urls',
        'reasoning',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_feedback',
        'quality_score',
        'is_controversial',
        'requires_expert_review',
    ];

    protected $casts = [
        'proposed_data' => 'array',
        'current_data' => 'array',
        'proof_urls' => 'array',
        'quality_score' => 'decimal:2',
        'is_controversial' => 'boolean',
        'requires_expert_review' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function approve(int $reviewerId, ?string $feedback = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'reviewer_feedback' => $feedback,
        ]);

        // Update user statistics
        $this->user->increment('approved_contributions');
        $this->user->increment('contributions_count');
        $this->updateUserAccuracy();
        $this->updateUserTrustScore();
    }

    public function reject(int $reviewerId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'reviewer_feedback' => $reason,
        ]);

        // Update user statistics
        $this->user->increment('rejected_contributions');
        $this->user->increment('contributions_count');
        $this->updateUserAccuracy();
        $this->updateUserTrustScore();
    }

    public function requestMoreInfo(int $reviewerId, string $message): void
    {
        $this->update([
            'status' => 'needs_info',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'reviewer_feedback' => $message,
        ]);
    }

    protected function updateUserAccuracy(): void
    {
        $user = $this->user;

        if ($user->contributions_count === 0) {
            return;
        }

        $accuracy = ($user->approved_contributions / $user->contributions_count) * 100;
        $user->update(['contribution_accuracy' => round($accuracy, 2)]);
    }

    protected function updateUserTrustScore(): void
    {
        $user = $this->user;

        // Simple trust score calculation
        // Base: 50, +1 for each approval, -2 for each rejection
        $score = 50 + $user->approved_contributions - ($user->rejected_contributions * 2);
        $score = max(0, min(100, $score)); // Clamp between 0-100

        $user->update(['trust_score' => $score]);
    }
}
