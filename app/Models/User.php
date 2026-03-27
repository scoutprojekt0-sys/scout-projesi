<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'city',
        'phone',
        'stripe_customer_id',
        'paypal_customer_id',
        'subscription_status',
        'is_public',
        'position',
        'country',
        'age',
        'photo_url',
        'views_count',
        'rating',
        'source_url',
        'confidence_score',
        'verified_at',
        'verification_status',
        'verification_notes',
        'last_updated_by',
        'data_version',
        'has_source',
        'has_conflicts',
        'editor_role',
        'contributions_count',
        'approved_contributions',
        'rejected_contributions',
        'contribution_accuracy',
        'trust_score',
        'scout_points',
        'scout_tips_count',
        'successful_tips_count',
        'scout_accuracy_rate',
        'scout_rank',
        'editor_since',
        'reviews_count',
        'avg_review_time_hours',
        'can_verify_critical',
        'can_dual_approve',
        'is_verified',
        'email_verified_at',
        'email_verification_token',
        'player_password_initialized',
    ];

    protected $hidden = [
        'password',
        'stripe_customer_id',
        'paypal_customer_id',
        'email_verification_token',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'age' => 'integer',
        'views_count' => 'integer',
        'rating' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'verified_at' => 'datetime',
        'has_source' => 'boolean',
        'has_conflicts' => 'boolean',
        'contribution_accuracy' => 'decimal:2',
        'trust_score' => 'decimal:2',
        'scout_accuracy_rate' => 'decimal:2',
        'editor_since' => 'datetime',
        'can_verify_critical' => 'boolean',
        'can_dual_approve' => 'boolean',
        'is_verified' => 'boolean',
        'email_verified_at' => 'datetime',
        'player_password_initialized' => 'boolean',
    ];

    public function playerProfile()
    {
        return $this->hasOne(PlayerProfile::class);
    }

    public function socialMediaAccounts()
    {
        return $this->hasMany(SocialMediaAccount::class);
    }

    public function teamProfile()
    {
        return $this->hasOne(TeamProfile::class);
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function boosts()
    {
        return $this->hasMany(PlayerBoost::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'team_user_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'player_user_id');
    }

    public function sentContacts()
    {
        return $this->hasMany(Contact::class, 'from_user_id');
    }

    public function receivedContacts()
    {
        return $this->hasMany(Contact::class, 'to_user_id');
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedBy()
    {
        return $this->hasMany(Favorite::class, 'target_user_id');
    }

    public function playerSearches()
    {
        return $this->hasMany(PlayerSearch::class, 'manager_id');
    }

    public function lawyer()
    {
        return $this->hasOne(Lawyer::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function profileViews()
    {
        return $this->hasMany(ProfileView::class, 'viewed_user_id');
    }

    public function writtenProfileReviews()
    {
        return $this->hasMany(ProfileReview::class, 'author_id');
    }

    public function receivedProfileReviews()
    {
        return $this->hasMany(ProfileReview::class, 'target_id');
    }

    public function viewedProfiles()
    {
        return $this->hasMany(ProfileView::class, 'viewer_user_id');
    }

    public function scoutTips()
    {
        return $this->hasMany(ScoutTip::class, 'submitted_by');
    }

    public function scoutPointEntries()
    {
        return $this->hasMany(ScoutPointLedger::class);
    }

    public function scoutRewards()
    {
        return $this->hasMany(ScoutReward::class);
    }

    public function scoutTipWatchlistEntries()
    {
        return $this->hasMany(ScoutTipWatchlist::class, 'manager_user_id');
    }

    public function requestedVideoAnalyses()
    {
        return $this->hasMany(VideoAnalysis::class, 'requested_by');
    }

    public function playerVideoMetrics()
    {
        return $this->hasMany(PlayerVideoMetric::class, 'player_id');
    }

    protected static function booted(): void
    {
        static::updated(function (self $user): void {
            if ($user->wasChanged('role')) {
                $user->tokens()->delete();
            }
        });
    }

    public function tokenAbilities(): array
    {
        $abilities = [
            'profile:read',
            'profile:write',
            'media:read',
            'media:write',
            'contact:read',
            'contact:write',
        ];

        return match ($this->role) {
            'player' => array_merge($abilities, ['player', 'application:apply', 'application:outgoing']),
            'team' => array_merge($abilities, ['team', 'staff', 'opportunity:write', 'application:incoming']),
            'manager' => array_merge($abilities, ['staff', 'opportunity:write', 'application:incoming']),
            'coach', 'scout' => array_merge($abilities, ['staff']),
            'lawyer' => array_merge($abilities, ['staff', 'lawyer']),
            default => $abilities,
        };
    }
}
