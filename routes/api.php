<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AdminBillingController;
use App\Http\Controllers\Api\AdminAmateurResultController;
use App\Http\Controllers\Api\AdminScoutController;
use App\Http\Controllers\Api\ApiRootController;
use App\Http\Controllers\Api\AiSupportController;
use App\Http\Controllers\Api\AiLabelingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\ClubController;
use App\Http\Controllers\Api\ClubWorkspaceController;
use App\Http\Controllers\Api\CoachPlayerClipController;
use App\Http\Controllers\Api\CoachPlayerEvaluationController;
use App\Http\Controllers\Api\CoachPlayerNoteController;
use App\Http\Controllers\Api\StaffPlayerClipController;
use App\Http\Controllers\Api\StaffPlayerEvaluationController;
use App\Http\Controllers\Api\StaffPlayerNoteController;
use App\Http\Controllers\Api\DataQualityController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FeaturedController;
use App\Http\Controllers\Api\HelpController;
use App\Http\Controllers\Api\LawyerController;
use App\Http\Controllers\Api\LawyerWorkspaceController;
use App\Http\Controllers\Api\LeagueController;
use App\Http\Controllers\Api\LegacyCompatibilityController;
use App\Http\Controllers\Api\LiveMatchController;
use App\Http\Controllers\Api\LocalizationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MarketTerminalController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OpportunityController;
use App\Http\Controllers\Api\PlayerAnalyticsController;
use App\Http\Controllers\Api\PlayerCareerController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\PlayerMarketValueController;
use App\Http\Controllers\Api\ProfileReviewController;
use App\Http\Controllers\Api\PlayerSearchController;
use App\Http\Controllers\Api\PlayerTransferController;
use App\Http\Controllers\Api\PlayerVideoMetricController;
use App\Http\Controllers\Api\PublicContactMessageController;
use App\Http\Controllers\Api\ProfileViewController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScoutingSearchController;
use App\Http\Controllers\Api\ScoutPlayerReportController;
use App\Http\Controllers\Api\ScoutRewardController;
use App\Http\Controllers\Api\ScoutScoreboardController;
use App\Http\Controllers\Api\ScoutTipController;
use App\Http\Controllers\Api\SocialMediaController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TrendingController;
use App\Http\Controllers\Api\VideoAnalysisController;
use App\Http\Controllers\Api\VideoClipController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\Week7AnalyticsController;
use App\Http\Controllers\Api\Week8TransparencyController;
use App\Http\Controllers\Api\Week10AnomalyController;
use App\Http\Controllers\Api\Week11WorkloadController;
use App\Http\Controllers\Api\Week12PublicTransparencyController;
use App\Http\Controllers\Api\WatchDemandController;
use Illuminate\Support\Facades\Route;

// System endpoints
Route::get('/', ApiRootController::class);
Route::get('/ping', [SystemController::class, 'ping']);
Route::get('/locales', [LocalizationController::class, 'getSupportedLocales']);
Route::get('/translations', [LocalizationController::class, 'getTranslations']);
Route::middleware('internal_tool')->prefix('ai-labeling')->group(function () {
    Route::get('/{sport}/queue', [AiLabelingController::class, 'queue']);
    Route::get('/image', [AiLabelingController::class, 'image']);
    Route::post('/{sport}/predict', [AiLabelingController::class, 'predict']);
    Route::post('/{sport}/save', [AiLabelingController::class, 'save']);
    Route::post('/{sport}/skip', [AiLabelingController::class, 'skip']);
});

// Webhook endpoints (CSRF'den muaf, imza ile korunuyor)
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe'])->withoutMiddleware([\App\Http\Middleware\SanitizeInput::class]);
Route::post('/webhooks/paypal', [WebhookController::class, 'paypal'])->withoutMiddleware([\App\Http\Middleware\SanitizeInput::class]);
Route::post('/webhooks/iyzico', [WebhookController::class, 'iyzico'])->withoutMiddleware([\App\Http\Middleware\SanitizeInput::class]);
Route::post('/video-analyses/{id}/callback', [VideoAnalysisController::class, 'callback'])
    ->withoutMiddleware([\App\Http\Middleware\SanitizeInput::class])
    ->name('video-analyses.callback');

// Week 12 - Public Transparency (no auth required)
Route::get('/transparency/trust-report', [Week12PublicTransparencyController::class, 'platformTrustReport']);

// Data Quality & Trust endpoints (Week 1)
Route::prefix('data-quality')->group(function () {
    Route::get('/dashboard', [DataQualityController::class, 'dashboard']);
    Route::get('/report', [DataQualityController::class, 'report']);
    Route::get('/source-health', [Week8TransparencyController::class, 'sourceHealth']);
    Route::get('/transparency/players', [Week8TransparencyController::class, 'players']);
    Route::get('/transparency/players/{playerId}', [Week8TransparencyController::class, 'playerDetail']);
});

Route::prefix('data-quality')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/audit-log', [DataQualityController::class, 'auditLog']);
    Route::get('/conflicts', [DataQualityController::class, 'conflictingData']);
    Route::get('/missing-source', [DataQualityController::class, 'missingSource']);
});

// Moderation Queue endpoints
Route::prefix('moderation')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api', 'ability:staff'])->group(function () {
    Route::get('/', [ModerationController::class, 'index']);
    Route::get('/high-risk', [Week10AnomalyController::class, 'highRiskQueue']);
    Route::get('/stats', [ModerationController::class, 'stats']);
    Route::get('/{id}', [ModerationController::class, 'show']);
    Route::post('/{id}/score', [Week10AnomalyController::class, 'scoreQueue']);
    Route::post('/{id}/approve', [ModerationController::class, 'approve']);
    Route::post('/{id}/reject', [ModerationController::class, 'reject']);
    Route::post('/{id}/flag', [ModerationController::class, 'flag']);
});

// Player Transfer endpoints
Route::prefix('transfers')->group(function () {
    Route::get('/', [PlayerTransferController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/{id}', [PlayerTransferController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/player/{playerId}/timeline', [PlayerTransferController::class, 'timeline']);
    Route::post('/', [PlayerTransferController::class, 'store'])->middleware('auth:sanctum');
    Route::post('/{id}/room-action', [PlayerTransferController::class, 'roomAction'])->middleware('auth:sanctum');
});
Route::get('/players/me/offer-desk', [PlayerTransferController::class, 'offerDesk'])->middleware(['auth:sanctum', 'ability:player']);

// Player Career Timeline endpoints
Route::prefix('career')->group(function () {
    Route::get('/player/{playerId}/timeline', [PlayerCareerController::class, 'timeline']);
    Route::get('/player/{playerId}/statistics', [PlayerCareerController::class, 'statistics']);
    Route::get('/player/{playerId}/activity', [PlayerCareerController::class, 'activity'])->middleware('auth:sanctum');
    Route::post('/', [PlayerCareerController::class, 'store'])->middleware('auth:sanctum');
});

// Week 6 - Player analytics endpoints
Route::prefix('players')->group(function () {
    Route::post('/compare', [PlayerAnalyticsController::class, 'compare']);
    Route::get('/{playerId}/trend-summary', [PlayerAnalyticsController::class, 'trendSummary']);
    Route::get('/{playerId}/similar', [PlayerAnalyticsController::class, 'similar']);
    Route::get('/me/share-assets', [PlayerController::class, 'shareAssets'])->middleware('auth:sanctum');
    Route::get('/me/export-pdf', [PlayerController::class, 'exportPdf'])->middleware('auth:sanctum');
});

// Player Market Value endpoints
Route::prefix('market-values')->group(function () {
    Route::get('/', [PlayerMarketValueController::class, 'index']);
    Route::get('/leaderboard', [PlayerMarketValueController::class, 'leaderboard']);
    Route::get('/player/{playerId}/history', [PlayerMarketValueController::class, 'history']);
    Route::get('/player/{playerId}/calculate', [PlayerMarketValueController::class, 'calculate']);
    Route::get('/player/{playerId}/trends', [PlayerMarketValueController::class, 'trends']);
    Route::post('/compare', [PlayerMarketValueController::class, 'compare']);
    Route::post('/', [PlayerMarketValueController::class, 'store'])->middleware('auth:sanctum');
});

// User Contributions endpoints (Week 3)
Route::prefix('contributions')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ContributionController::class, 'index']);
    Route::get('/my', [ContributionController::class, 'myContributions']);
    Route::get('/stats', [ContributionController::class, 'stats']);
    Route::get('/{id}', [ContributionController::class, 'show']);
    Route::post('/', [ContributionController::class, 'store']);
    Route::post('/{id}/approve', [ContributionController::class, 'approve']);
    Route::post('/{id}/reject', [ContributionController::class, 'reject']);
    Route::post('/{id}/request-info', [ContributionController::class, 'requestInfo']);
});

Route::get('/scout-tips/feed', [ScoutTipController::class, 'feed'])->middleware('throttle:api');
Route::prefix('scout-tips')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    Route::get('/', [ScoutTipController::class, 'index'])->middleware('ability:profile:read');
    Route::get('/my', [ScoutTipController::class, 'my'])->middleware('ability:profile:read');
    Route::get('/resolve-player', [ScoutTipController::class, 'resolvePlayer'])->middleware('ability:profile:read');
    Route::get('/staff-inbox', [ScoutTipController::class, 'staffInbox'])->middleware('ability:profile:read');
    Route::get('/watchlist/my', [ScoutTipController::class, 'watchlist'])->middleware('ability:profile:read');
    Route::get('/role-requests/feed', [ScoutTipController::class, 'roleRequestFeed'])->middleware('ability:profile:read');
    Route::get('/role-requests/my', [ScoutTipController::class, 'myRoleRequests'])->middleware('ability:profile:read');
    Route::get('/{id}', [ScoutTipController::class, 'show'])->middleware('ability:profile:read');
    Route::post('/watchlist/{id}/remove', [ScoutTipController::class, 'removeFromWatchlist'])->middleware('ability:profile:write');
    Route::post('/{id}/staff-review', [ScoutTipController::class, 'recordStaffReview'])->middleware('ability:profile:write');
    Route::post('/{id}/manager-note', [ScoutTipController::class, 'saveManagerNote'])->middleware('ability:profile:write');
    Route::post('/{id}/watchlist', [ScoutTipController::class, 'addToWatchlist'])->middleware('ability:profile:write');
    Route::post('/{id}/role-request', [ScoutTipController::class, 'requestRole'])->middleware('ability:profile:write');
    Route::post('/', [ScoutTipController::class, 'store'])->middleware('ability:profile:write');
    Route::post('/{id}/withdraw', [ScoutTipController::class, 'withdraw'])->middleware('ability:profile:write');
    Route::post('/{id}/screen', [ScoutTipController::class, 'screen'])->middleware('ability:staff');
    Route::post('/{id}/shortlist', [ScoutTipController::class, 'shortlist'])->middleware('ability:staff');
    Route::post('/{id}/approve', [ScoutTipController::class, 'approve'])->middleware('ability:staff');
    Route::post('/{id}/reject', [ScoutTipController::class, 'reject'])->middleware('ability:staff');
    Route::post('/{id}/mark-trial', [ScoutTipController::class, 'markTrial'])->middleware('ability:staff');
    Route::post('/{id}/mark-signed', [ScoutTipController::class, 'markSigned'])->middleware('ability:staff');
});

Route::post('/scout-tips/guest', [ScoutTipController::class, 'storeGuest'])->middleware('throttle:api');
Route::post('/media/guest', [MediaController::class, 'guestStore'])->middleware('throttle:api');
Route::post('/public/contact-messages', [PublicContactMessageController::class, 'store'])->middleware('throttle:api');

Route::prefix('scout-scoreboard')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    Route::get('/me', [ScoutScoreboardController::class, 'me'])->middleware('ability:profile:read');
    Route::get('/leaderboard', [ScoutScoreboardController::class, 'leaderboard'])->middleware('ability:profile:read');
});

Route::prefix('scout-rewards')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    Route::get('/my', [ScoutRewardController::class, 'my'])->middleware('ability:profile:read');
});

Route::prefix('scout-player-reports')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    Route::get('/', [ScoutPlayerReportController::class, 'index'])->middleware('ability:profile:read');
    Route::post('/', [ScoutPlayerReportController::class, 'store'])->middleware('ability:profile:write');
    Route::post('/{id}/status', [ScoutPlayerReportController::class, 'updateStatus'])->middleware('ability:profile:write');
});

Route::prefix('video-analyses')->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    Route::post('/start', [VideoAnalysisController::class, 'start'])->middleware('ability:profile:write');
    Route::get('/{id}', [VideoAnalysisController::class, 'show'])->middleware('ability:profile:read');
    Route::get('/{id}/events', [VideoAnalysisController::class, 'events'])->middleware('ability:profile:read');
    Route::get('/{id}/clips', [VideoAnalysisController::class, 'clips'])->middleware('ability:profile:read');
});

Route::get('/players/{playerId}/video-metrics', [PlayerVideoMetricController::class, 'index'])
    ->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api', 'ability:profile:read']);

Route::get('/scouting-search/status', [ScoutingSearchController::class, 'status']);
Route::get('/scouting-search/discovery', [ScoutingSearchController::class, 'discovery']);
Route::get('/scouting-search/rankings', [ScoutingSearchController::class, 'rankings']);
Route::get('/scouting-search/video-metrics', [ScoutingSearchController::class, 'videoMetrics'])
    ->middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api', 'ability:profile:read']);

Route::get('/live-matches/count', [LiveMatchController::class, 'getCount']);
Route::get('/live-matches', [LiveMatchController::class, 'liveMatches']);
Route::get('/match-center/live-matches', [LiveMatchController::class, 'liveMatches']);
Route::get('/matches/recent', [LiveMatchController::class, 'recentResults']);
Route::get('/recent-results', [LiveMatchController::class, 'recentResults']);
Route::get('/matches/upcoming', [LiveMatchController::class, 'upcomingMatches']);
Route::get('/upcoming-matches', [LiveMatchController::class, 'upcomingMatches']);
Route::get('/matches/{matchId}', [LiveMatchController::class, 'matchDetails']);
Route::get('/match/{matchId}/details', [LiveMatchController::class, 'matchDetails']);
Route::get('/matches/{matchId}/scorers', [LiveMatchController::class, 'matchScorers']);
Route::get('/match/{matchId}/scorers', [LiveMatchController::class, 'matchScorers']);

// Public Discovery Endpoints
Route::get('/public/players', [DiscoveryController::class, 'publicPlayers']);
Route::get('/public/search', [DiscoveryController::class, 'globalSearch']);
Route::get('/public/players/{id}/profile', [PlayerController::class, 'publicProfile']);
Route::get('/public/players/{id}/media', [MediaController::class, 'publicIndexByUser']);
Route::get('/contracts/live', [DiscoveryController::class, 'contractsLive']);
Route::get('/player-of-week', [DiscoveryController::class, 'playerOfWeek']);
Route::get('/trending/week', [DiscoveryController::class, 'trendingWeek']);
Route::get('/trending/today', [TrendingController::class, 'getTodayTrending']);
Route::post('/trending/track', [TrendingController::class, 'trackInteraction']);
Route::get('/featured', [FeaturedController::class, 'getFeatured']);
Route::get('/featured/rising-stars', [FeaturedController::class, 'getRisingStars']);
Route::get('/featured/hot-transfers', [FeaturedController::class, 'getHotTransfers']);
Route::get('/featured/player-of-week', [FeaturedController::class, 'getPlayerOfWeek']);
Route::get('/help/categories', [HelpController::class, 'getCategories']);
Route::get('/help/categories/{categorySlug}/articles', [HelpController::class, 'getCategoryArticles']);
Route::get('/help/articles/{slug}', [HelpController::class, 'getArticle']);
Route::post('/help/articles/{slug}/helpful', [HelpController::class, 'markArticleHelpful']);
Route::post('/help/articles/{slug}/unhelpful', [HelpController::class, 'markArticleUnhelpful']);
Route::get('/help/faq', [HelpController::class, 'getFaq']);
Route::post('/help/faq/{faqId}/helpful', [HelpController::class, 'markFaqHelpful']);
Route::get('/help/search', [HelpController::class, 'search']);
Route::get('/rising-stars', [DiscoveryController::class, 'risingStars']);
Route::get('/club-needs', [DiscoveryController::class, 'clubNeeds']);
Route::get('/public/favorites/leaderboard', [FavoriteController::class, 'publicLeaderboard']);
Route::get('/public/new-professionals', [DiscoveryController::class, 'newProfessionals']);
Route::get('/public/turkiye-heatmap', [DiscoveryController::class, 'publicTurkeyHeatmap']);
Route::get('/public/live-watch-heatmap', [WatchDemandController::class, 'publicHeatmap']);
Route::get('/market/live-feed', [MarketTerminalController::class, 'liveFeed']);
Route::get('/public/players/quality-summary', [LegacyCompatibilityController::class, 'publicPlayersQualitySummary']);
Route::get('/community-events', [LegacyCompatibilityController::class, 'communityEventsIndex']);
Route::get('/community-events/{id}', [LegacyCompatibilityController::class, 'communityEventsShow']);
Route::get('/success-stories', [LegacyCompatibilityController::class, 'successStoriesIndex']);
Route::prefix('discovery')->group(function () {
    Route::get('/manager-needs', [DiscoveryController::class, 'managerNeeds']);
    Route::get('/coach-needs', [LegacyCompatibilityController::class, 'discoveryCoachNeeds']);
    Route::get('/boosts', [LegacyCompatibilityController::class, 'discoveryBoosts']);
    Route::get('/weekly-digest', [DiscoveryController::class, 'weeklyDigest']);
});

// Public News & Billing
Route::get('/news/live', [NewsController::class, 'live']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/billing/plans', [BillingController::class, 'plans']);
Route::get('/billing/boost-packages', [BillingController::class, 'boostPackages']);
Route::get('/teams/{id}/overview', [TeamController::class, 'overview']);
Route::get('/clubs', [ClubController::class, 'index']);
Route::get('/clubs/most-valuable', [ClubController::class, 'mostValuable']);
Route::get('/clubs/{id}', [ClubController::class, 'show']);
Route::get('/clubs/{id}/squad', [ClubController::class, 'squad']);
Route::get('/clubs/{id}/transfers', [ClubController::class, 'transfers']);
Route::get('/leagues', [LeagueController::class, 'index']);
Route::get('/leagues/{league}', [LeagueController::class, 'show']);
Route::get('/leagues/{league}/standings', [LeagueController::class, 'standings']);
Route::get('/leagues/{league}/top-scorers', [LeagueController::class, 'topScorers']);
Route::get('/leagues/{league}/top-assists', [LeagueController::class, 'topAssists']);
Route::get('/lawyers', [LawyerController::class, 'publicIndex']);
Route::get('/lawyers/{lawyerId}', [LawyerController::class, 'show']);
Route::get('/profiles/{userId}/views/count', [ProfileViewController::class, 'viewCount']);
Route::get('/profiles/{userId}/reviews', [ProfileReviewController::class, 'index']);
Route::get('/users/{userId}/videos', [VideoClipController::class, 'index']);
Route::get('/profile-cards/{cardType}/{cardOwnerId}/stats', [LegacyCompatibilityController::class, 'profileCardStats']);
Route::get('/video-portfolio/player/{playerId}', [LegacyCompatibilityController::class, 'playerVideoPortfolio']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/player/login', [AuthController::class, 'playerLogin'])->middleware('throttle:auth');
    Route::post('/player/set-password', [AuthController::class, 'playerSetPassword'])->middleware('throttle:auth');
    Route::post('/supabase/exchange', [AuthController::class, 'exchangeSupabaseToken'])->middleware('throttle:auth');
    Route::get('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:auth');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:auth');
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('ability:profile:write');
        Route::get('/sessions', [AuthController::class, 'sessions'])->middleware('ability:profile:read');
        Route::delete('/sessions', [AuthController::class, 'logoutAll'])->middleware('ability:profile:write');
        Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->middleware('ability:profile:write');
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateMe']);
    });
});

Route::middleware(['auth:sanctum', 'reject_legacy_token', 'throttle:api'])->group(function () {
    // Week 7 analytics
    Route::get('/analytics/admin-overview', [Week7AnalyticsController::class, 'adminOverview'])->middleware('admin');
    Route::get('/analytics/team/{teamId}', [Week7AnalyticsController::class, 'teamScoutingFunnel']);

    // System
    Route::get('/notifications/count', [SystemController::class, 'notificationsCount']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::get('/users', [SystemController::class, 'usersIndex'])->middleware('admin');
    Route::get('/users/{id}/profile-card', [SystemController::class, 'userProfileCard'])->middleware('admin');
    Route::get('/admin/ops/rate-limit-summary', [SystemController::class, 'adminRateLimitSummary'])->middleware('admin');
    Route::get('/admin/success-stories', [LegacyCompatibilityController::class, 'adminSuccessStoriesIndex'])->middleware('admin');
    Route::patch('/admin/success-stories/{id}', [LegacyCompatibilityController::class, 'adminSuccessStoriesUpdate'])->middleware('admin');

    // Players, Teams, Staff
    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{id}', [PlayerController::class, 'show']);
    Route::put('/players/{id}', [PlayerController::class, 'update'])->middleware('ability:player');
    Route::patch('/players/{id}', [PlayerController::class, 'update'])->middleware('ability:player');
    Route::apiResource('teams', TeamController::class)->only(['index', 'show', 'update']);
    Route::get('/teams/{id}/transfer-summary', [TeamController::class, 'transferSummary']);

    Route::post('/media', [MediaController::class, 'store'])->middleware('ability:media:write');
    Route::get('/users/{id}/media', [MediaController::class, 'indexByUser'])->middleware('ability:media:read');
    Route::delete('/media/{id}', [MediaController::class, 'destroy'])->middleware('ability:media:write');

    Route::get('/opportunities', [OpportunityController::class, 'index']);
    Route::get('/opportunities/{id}', [OpportunityController::class, 'show']);
    Route::post('/opportunities', [OpportunityController::class, 'store'])->middleware('ability:opportunity:write');
    Route::put('/opportunities/{id}', [OpportunityController::class, 'update'])->middleware('ability:opportunity:write');
    Route::patch('/opportunities/{id}', [OpportunityController::class, 'update'])->middleware('ability:opportunity:write');
    Route::delete('/opportunities/{id}', [OpportunityController::class, 'destroy'])->middleware('ability:opportunity:write');

    Route::post('/opportunities/{id}/apply', [ApplicationController::class, 'apply'])->middleware('ability:player');
    Route::get('/applications/incoming', [ApplicationController::class, 'incoming'])->middleware('ability:application:incoming');
    Route::get('/applications/outgoing', [ApplicationController::class, 'outgoing'])->middleware('ability:player');
    Route::patch('/applications/{id}/status', [ApplicationController::class, 'changeStatus'])->middleware('ability:application:incoming');

    Route::post('/contacts', [ContactController::class, 'store'])->middleware('ability:contact:write');
    Route::get('/contacts/recipients/search', [ContactController::class, 'searchRecipients'])->middleware('ability:contact:read');
    Route::get('/contacts/inbox', [ContactController::class, 'inbox'])->middleware('ability:contact:read');
    Route::get('/contacts/sent', [ContactController::class, 'sent'])->middleware('ability:contact:read');
    Route::patch('/contacts/{id}/status', [ContactController::class, 'changeStatus'])->middleware('ability:contact:write');

    Route::get('/player/match-schedules/my', [WatchDemandController::class, 'mySchedules'])->middleware('ability:player');
    Route::post('/player/match-schedules', [WatchDemandController::class, 'storeSchedule'])->middleware('ability:player');
    Route::delete('/player/match-schedules/{scheduleId}', [WatchDemandController::class, 'deleteSchedule'])->middleware('ability:player');

    Route::get('/watch-requests/my', [WatchDemandController::class, 'myRequests'])->middleware('ability:staff');
    Route::post('/watch-requests', [WatchDemandController::class, 'storeRequest'])->middleware('ability:staff');
    Route::get('/watch-requests/{requestId}/matches', [WatchDemandController::class, 'resolveRequest'])->middleware('ability:staff');
    Route::post('/messages', [ContactController::class, 'sendMessage'])->middleware('ability:contact:write');
    Route::get('/messages/inbox', [ContactController::class, 'inbox'])->middleware('ability:contact:read');
    Route::get('/discovery/matches-for-user', [DiscoveryController::class, 'matchesForUser'])->middleware('ability:profile:read');
    Route::get('/messages/sent', [ContactController::class, 'sent'])->middleware('ability:contact:read');
    Route::post('/messages/read-all', [ContactController::class, 'markAllAsRead'])->middleware('ability:contact:write');
    Route::patch('/messages/{id}/read', [ContactController::class, 'readMessage'])->middleware('ability:contact:write');
    Route::post('/messages/{id}/archive', [ContactController::class, 'archiveMessage'])->middleware('ability:contact:write');

    Route::post('/search/players', [PlayerSearchController::class, 'search'])->middleware('ability:profile:write');
    Route::get('/search/saved', [PlayerSearchController::class, 'getSavedSearches'])->middleware('ability:profile:read');
    Route::get('/search/{searchId}/results', [PlayerSearchController::class, 'getSearchResults'])->middleware('ability:profile:read');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('ability:profile:write');
    Route::get('/reports/my-reports', [ReportController::class, 'myReports'])->middleware('ability:profile:read');
    Route::get('/reports/{id}', [ReportController::class, 'show'])->middleware('ability:profile:read');
    Route::get('/support-tickets', [SupportTicketController::class, 'index'])->middleware('ability:profile:read');
    Route::post('/support-tickets', [SupportTicketController::class, 'store'])->middleware('ability:profile:write');
    Route::get('/support-tickets/{id}', [SupportTicketController::class, 'show'])->middleware('ability:profile:read');
    Route::post('/support-tickets/{id}/messages', [SupportTicketController::class, 'addMessage'])->middleware('ability:profile:write');
    Route::post('/support-tickets/{id}/close', [SupportTicketController::class, 'close'])->middleware('ability:profile:write');
    Route::post('/support/ai-chat', [AiSupportController::class, 'chat'])->middleware('ability:profile:read');
    Route::post('/live-matches', [LiveMatchController::class, 'store'])->middleware('ability:profile:write');
    Route::post('/match/{matchId}/live-update', [LiveMatchController::class, 'updateLiveMatch'])->middleware('ability:profile:write');

    Route::get('/favorites', [FavoriteController::class, 'index'])->middleware('ability:profile:read');
    Route::post('/favorites/{targetUserId}/toggle', [FavoriteController::class, 'toggle'])->middleware('ability:profile:write');
    Route::get('/favorites/{targetUserId}/check', [FavoriteController::class, 'check'])->middleware('ability:profile:read');
Route::get('/lawyers/private', [LawyerController::class, 'index'])->middleware('ability:profile:read');
Route::post('/lawyers/register', [LawyerController::class, 'register'])->middleware('ability:lawyer');
Route::put('/lawyers/{lawyerId}', [LawyerController::class, 'update'])->middleware('ability:lawyer');
Route::get('/lawyer/workspace', [LawyerWorkspaceController::class, 'index'])->middleware('ability:profile:read');
Route::patch('/lawyer/workspace/{itemId}/status', [LawyerWorkspaceController::class, 'updateStatus'])->middleware('ability:profile:write');
    Route::post('/profiles/{userId}/view', [ProfileViewController::class, 'track'])->middleware('ability:profile:write');
    Route::post('/profiles/{userId}/reviews', [ProfileReviewController::class, 'store'])->middleware('ability:profile:write');
Route::get('/coach/player-clips', [CoachPlayerClipController::class, 'index'])->middleware('ability:profile:read');
Route::post('/coach/player-clips', [CoachPlayerClipController::class, 'store'])->middleware('ability:profile:write');
Route::post('/coach/player-clips/{id}/analyze', [CoachPlayerClipController::class, 'analyze'])->middleware('ability:profile:write');
Route::get('/coach/player-evaluations', [CoachPlayerEvaluationController::class, 'index'])->middleware('ability:profile:read');
Route::post('/coach/player-evaluations', [CoachPlayerEvaluationController::class, 'store'])->middleware('ability:profile:write');
Route::get('/coach/player-notes', [CoachPlayerNoteController::class, 'index'])->middleware('ability:profile:read');
Route::post('/coach/player-notes', [CoachPlayerNoteController::class, 'store'])->middleware('ability:profile:write');
Route::get('/staff/player-clips', [StaffPlayerClipController::class, 'index'])->middleware('ability:profile:read');
Route::post('/staff/player-clips', [StaffPlayerClipController::class, 'store'])->middleware('ability:profile:write');
Route::get('/staff/player-evaluations', [StaffPlayerEvaluationController::class, 'index'])->middleware('ability:profile:read');
Route::post('/staff/player-evaluations', [StaffPlayerEvaluationController::class, 'store'])->middleware('ability:profile:write');
Route::get('/staff/player-notes', [StaffPlayerNoteController::class, 'index'])->middleware('ability:profile:read');
Route::post('/staff/player-notes', [StaffPlayerNoteController::class, 'store'])->middleware('ability:profile:write');
Route::apiResource('staff', StaffController::class)->only(['index', 'show', 'update']);
    Route::get('/scout/player-reports', [ScoutPlayerReportController::class, 'index'])->middleware('ability:profile:read');
    Route::post('/scout/player-reports', [ScoutPlayerReportController::class, 'store'])->middleware('ability:profile:write');
    Route::patch('/scout/player-reports/{id}/status', [ScoutPlayerReportController::class, 'updateStatus'])->middleware('ability:profile:write');
    Route::get('/club/offers', [ClubWorkspaceController::class, 'offersIndex'])->middleware('ability:profile:read');
    Route::post('/club/offers', [ClubWorkspaceController::class, 'offersStore'])->middleware('ability:profile:write');
    Route::get('/manager/offers', [ClubWorkspaceController::class, 'managerOffersIndex'])->middleware('ability:profile:read');
    Route::post('/manager/offers', [ClubWorkspaceController::class, 'managerOffersStore'])->middleware('ability:profile:write');
    Route::get('/club/promos', [ClubWorkspaceController::class, 'promosIndex'])->middleware('ability:profile:read');
    Route::post('/club/promos', [ClubWorkspaceController::class, 'promosStore'])->middleware('ability:profile:write');
    Route::get('/club/groups', [ClubWorkspaceController::class, 'groupsIndex'])->middleware('ability:profile:read');
    Route::post('/club/groups', [ClubWorkspaceController::class, 'groupsStore'])->middleware('ability:profile:write');
    Route::patch('/club/groups/{id}', [ClubWorkspaceController::class, 'groupsUpdate'])->middleware('ability:profile:write');
    Route::get('/club/internal-players', [ClubWorkspaceController::class, 'internalPlayersIndex'])->middleware('ability:profile:read');
    Route::post('/club/internal-players', [ClubWorkspaceController::class, 'internalPlayersStore'])->middleware('ability:profile:write');
    Route::put('/club/internal-players/{id}', [ClubWorkspaceController::class, 'internalPlayersUpdate'])->middleware('ability:profile:write');
    Route::delete('/club/internal-players/{id}', [ClubWorkspaceController::class, 'internalPlayersDestroy'])->middleware('ability:profile:write');
    Route::post('/club/internal-players/{id}/account', [ClubWorkspaceController::class, 'internalPlayersCreateAccount'])->middleware('ability:profile:write');
    Route::post('/club/internal-players/{id}/account/reset-password-setup', [ClubWorkspaceController::class, 'internalPlayersResetPasswordSetup'])->middleware('ability:profile:write');
    Route::get('/profiles/my-views', [ProfileViewController::class, 'myViews'])->middleware('ability:profile:read');
    Route::post('/profile-reviews/{reviewId}/reply', [ProfileReviewController::class, 'reply'])->middleware('ability:profile:write');
    Route::post('/profile-reviews/{reviewId}/report', [ProfileReviewController::class, 'report'])->middleware('ability:profile:write');
Route::patch('/profile-reviews/{reviewId}/status', [ProfileReviewController::class, 'moderate'])->middleware('admin');
Route::get('/admin/contact-messages', [PublicContactMessageController::class, 'index'])->middleware('admin');
Route::get('/admin/amateur-results', [AdminAmateurResultController::class, 'index'])->middleware('admin');
Route::post('/admin/amateur-results', [AdminAmateurResultController::class, 'store'])->middleware('admin');
Route::patch('/admin/amateur-results/{id}/status', [AdminAmateurResultController::class, 'updateStatus'])->middleware('admin');
Route::get('/admin/amateur-standings', [AdminAmateurResultController::class, 'standings'])->middleware('admin');
    Route::get('/featured/admin', [FeaturedController::class, 'adminList'])->middleware('admin');
    Route::post('/featured/admin', [FeaturedController::class, 'adminStore'])->middleware('admin');
    Route::patch('/featured/admin/{id}/active', [FeaturedController::class, 'adminToggleActive'])->middleware('admin');

    // Legacy frontend compatibility endpoints
    Route::post('/community-events/{id}/register', [LegacyCompatibilityController::class, 'communityEventsRegister']);
    Route::post('/success-stories', [LegacyCompatibilityController::class, 'successStoriesStore']);
    Route::post('/profile-cards/{cardType}/{cardOwnerId}/like', [LegacyCompatibilityController::class, 'profileCardLike']);

    // Billing
    Route::post('/billing/boost-purchase', [BillingController::class, 'boostPurchase'])->middleware('ability:player');
    Route::get('/billing/boost-status', [BillingController::class, 'boostStatus'])->middleware('ability:player');
    Route::get('/billing/boost-history', [BillingController::class, 'boostHistory'])->middleware('ability:player');

    // Admin Billing (Test/Development için)
    Route::get('/admin/billing/payments', [AdminBillingController::class, 'getPayments'])->middleware('admin');
    Route::get('/admin/billing/subscriptions', [AdminBillingController::class, 'getSubscriptions'])->middleware('admin');
    Route::get('/admin/billing/stats', [AdminBillingController::class, 'getPaymentStats'])->middleware('admin');
    Route::get('/admin/billing/boost-packages', [AdminBillingController::class, 'getBoostPackages'])->middleware('admin');
    Route::put('/admin/billing/boost-packages/{packageId}', [AdminBillingController::class, 'updateBoostPackage'])->middleware('admin');
    Route::get('/admin/scout-tips/queue', [AdminScoutController::class, 'queue'])->middleware('admin');
    Route::post('/admin/scout-tips/{tipId}/review', [AdminScoutController::class, 'review'])->middleware('admin');
    Route::get('/admin/scout-rewards', [AdminScoutController::class, 'rewards'])->middleware('admin');
    Route::post('/admin/scout-rewards/{rewardId}/approve', [AdminScoutController::class, 'approveReward'])->middleware('admin');
    Route::post('/admin/scout-rewards/{rewardId}/mark-paid', [AdminScoutController::class, 'markRewardPaid'])->middleware('admin');

    // Contracts
    Route::get('/contracts', [ContractController::class, 'index'])->middleware('auth:sanctum');
    Route::post('/contracts', [ContractController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/contracts/{id}', [ContractController::class, 'show'])->middleware('auth:sanctum');
    Route::patch('/contracts/{id}', [ContractController::class, 'update'])->middleware('auth:sanctum');

    // Social Media
    Route::get('/users/{userId}/social-media', [SocialMediaController::class, 'index']);
    Route::post('/social-media', [SocialMediaController::class, 'store'])->middleware('ability:profile:write');
    Route::patch('/social-media/{id}', [SocialMediaController::class, 'update'])->middleware('ability:profile:write');
    Route::delete('/social-media/{id}', [SocialMediaController::class, 'destroy'])->middleware('ability:profile:write');

    // Video Clips
    Route::get('/videos/trending', [VideoClipController::class, 'trending']);
    Route::get('/videos/tag/{tag}', [VideoClipController::class, 'byTag']);
    Route::get('/videos/{id}', [VideoClipController::class, 'show']);
    Route::post('/videos', [VideoClipController::class, 'store'])->middleware('ability:profile:write');
    Route::delete('/videos/{id}', [VideoClipController::class, 'destroy'])->middleware('ability:profile:write');

    // Player Statistics
    Route::get('/players/{playerId}/statistics', [\App\Http\Controllers\Api\PlayerStatisticsController::class, 'index']);
    Route::post('/player-statistics', [\App\Http\Controllers\Api\PlayerStatisticsController::class, 'store'])->middleware('ability:profile:write');
    Route::get('/seasons/{season}/top-scorers', [\App\Http\Controllers\Api\PlayerStatisticsController::class, 'topScorers']);

    // Week 11 - Reviewer Workload & SLA
    Route::get('/analytics/reviewer-workload', [Week11WorkloadController::class, 'reviewerWorkload'])->middleware('admin');
    Route::get('/analytics/sla-dashboard', [Week11WorkloadController::class, 'slaDashboard'])->middleware('admin');
});
