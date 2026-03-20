# Crowdsourced Scout Network Plan

## Goal

Build a volunteer-driven local scouting network on top of the existing Scout Projekt platform.

Core idea:

- A user records a local player or match clip.
- The user submits a structured scout tip.
- The platform scores the submission quality and the submitter reliability.
- Moderators and pro scouts review the best tips.
- If a player advances to trial, academy, contract, or transfer, the original submitter earns points and potentially a reward.

This should be implemented as an extension of the existing platform, not as a separate product.

## What Already Exists

The current backend already has core building blocks that fit this model:

- `video_clips`: stores uploaded or linked match clips.
- `user_contributions`: stores user-submitted structured changes or discoveries.
- `moderation_queue`: supports review and approval flow.
- `player_transfers`: can later power success attribution and reward triggers.

This means the fastest path is to introduce a dedicated "scout tip" domain layer and connect it to the current moderation and player lifecycle system.

## Recommended Domain Model

Add a new first-class entity instead of overloading `user_contributions`.

### New tables

1. `scout_tips`
- `id`
- `submitted_by`
- `player_id` nullable
- `video_clip_id` nullable
- `status` enum: `pending`, `screened`, `shortlisted`, `approved`, `rejected`, `signed`, `rewarded`
- `source_type` enum: `new_player`, `existing_player`
- `player_name`
- `birth_year` nullable
- `position` nullable
- `foot` nullable
- `height_cm` nullable
- `city`
- `district` nullable
- `neighborhood` nullable
- `team_name` nullable
- `competition_level` nullable
- `match_date` nullable
- `guardian_consent_status` enum: `not_required`, `pending`, `received`, `rejected`
- `description`
- `ai_quality_score`
- `review_score`
- `final_score`
- `duplicate_of_tip_id` nullable
- `metadata` json
- timestamps

2. `scout_tip_events`
- immutable event log for lifecycle changes
- `tip_created`, `screened`, `duplicate_detected`, `trial_invite`, `signed`, `reward_issued`

3. `scout_point_ledger`
- `user_id`
- `scout_tip_id`
- `event_type`
- `points`
- `notes`

4. `scout_rewards`
- `user_id`
- `scout_tip_id`
- `reward_type` enum: `cash_bonus`, `wallet_credit`, `commission_share`, `gift`, `badge`
- `status` enum: `pending`, `approved`, `paid`, `cancelled`
- `amount` nullable
- `currency` nullable
- `basis` enum: `trial`, `academy_acceptance`, `pro_contract`, `verified_transfer`
- `metadata` json

## How It Fits Existing Tables

### `video_clips`

Use it for raw evidence. A scout volunteer uploads or links the match clip first, then references that clip from `scout_tips.video_clip_id`.

### `user_contributions`

Keep it for general community data edits. Do not force scout submissions into this table long-term. Instead:

- optionally create a mirrored contribution record when a scout tip updates a player profile
- keep scout discovery as a dedicated workflow

### `moderation_queue`

Each new `scout_tip` should automatically create a moderation entry.

Recommended moderation reasons:

- `new_entry`
- `low_confidence`
- `conflict_detected`
- `automated_flag`

### `player_transfers`

Use this later to trigger milestone rewards. If a player tied to a scout tip signs or transfers, the system can calculate a reward candidate for the first valid submitter.

## Product Flow

### Phase 1: Tip intake

Mobile flow:

1. User taps `Scout Et`.
2. Uploads or links a match clip.
3. Fills player and match details.
4. Accepts legal confirmation.
5. Submission creates:
   - `video_clips` row
   - `scout_tips` row
   - `moderation_queue` row

### Phase 2: Screening

Automatic checks:

- duplicate clip detection
- duplicate player detection
- minimum clip duration
- suspicious metadata checks
- submitter trust score impact

Outputs:

- `ai_quality_score`
- moderation priority
- duplicate flag

### Phase 3: Human review

Backoffice roles:

- moderator: content validity
- scout analyst: football quality
- admin: reward eligibility and fraud disputes

Decision outcomes:

- reject
- request more info
- shortlist
- link to existing player
- create new player profile

### Phase 4: Attribution and rewards

When a player reaches a milestone:

- trial invite
- academy entry
- first official contract
- verified professional transfer

the system writes a `scout_tip_event`, adds points to `scout_point_ledger`, and optionally opens a `scout_rewards` record.

## Scoring Model

Do not reward upload volume. Reward validated signal.

Suggested point schedule:

- valid submission: `+5`
- not duplicate and reviewable: `+10`
- shortlisted by scout team: `+25`
- invited to trial: `+100`
- signs academy or pro deal: `+500`
- verified transfer after discovery: `+1000`
- rejected as spam or fraud: `-20`

User-level metrics:

- `scout_points`
- `scout_accuracy_rate`
- `scout_tips_count`
- `successful_tips_count`
- `scout_rank`

These can live on `users` as cached counters, with the ledger as source of truth.

## Legal and Risk Controls

This feature touches minors, identity, and compensation. It must not launch without guardrails.

Required controls:

- guardian consent workflow for minors
- explicit rights confirmation for uploaded footage
- anti-spam rate limiting
- duplicate detection before moderator review
- fraud review before reward payout
- audit trail for every status transition

Important business rule:

Prefer "success reward" wording over "transfer commission" in the first release. Direct transfer commission can create legal and licensing issues depending on jurisdiction and football regulations.

## API Plan

Add a dedicated route group:

- `POST /api/scout-tips`
- `GET /api/scout-tips/my`
- `GET /api/scout-tips/{id}`
- `POST /api/scout-tips/{id}/withdraw`
- `POST /api/scout-tips/{id}/screen`
- `POST /api/scout-tips/{id}/shortlist`
- `POST /api/scout-tips/{id}/reject`
- `POST /api/scout-tips/{id}/mark-trial`
- `POST /api/scout-tips/{id}/mark-signed`
- `GET /api/scout-scoreboard/me`
- `GET /api/scout-scoreboard/leaderboard`
- `GET /api/scout-rewards/my`

Suggested controllers:

- `ScoutTipController`
- `ScoutScoreboardController`
- `ScoutRewardController`

Suggested services:

- `ScoutTipScoringService`
- `ScoutDuplicateDetectionService`
- `ScoutRewardService`
- `ScoutAttributionService`

## Mobile App Plan

Add these screens in `scout_mobile`:

- `Scout Tip Submit`
- `My Scout Tips`
- `Scout Points`
- `Scout Rewards`
- `Moderator Review` only if internal users will use mobile

MVP mobile fields:

- player name
- age or birth year
- city
- district
- position
- team name
- clip URL or upload
- short note: why this player matters

## Delivery Order

### Sprint 1

- add migrations for `scout_tips`, `scout_tip_events`, `scout_point_ledger`, `scout_rewards`
- add models and policies
- add create/list/show endpoints
- connect `scout_tips` to `moderation_queue`

### Sprint 2

- add scoring service
- add duplicate detection rules
- add scout points ledger writes
- expose `my points` and `leaderboard`

### Sprint 3

- add milestone attribution from player trial, contract, or transfer events
- add rewards workflow
- add admin review for payout approval

### Sprint 4

- improve trust and fraud controls
- add analytics dashboards
- tune ranking and recommendation logic

## Best MVP For This Project

The cleanest MVP is:

1. volunteer uploads a clip
2. volunteer submits a scout tip
3. moderators review it
4. approved tips earn points
5. shortlisted or signed players generate bonus points

Do not build revenue-share automation in version 1.
First prove:

- users submit real tips
- moderators can process them
- clubs care about shortlisted leads
- the platform can measure attribution cleanly

## Recommendation

For this codebase, the right implementation path is:

- keep using current `video_clips`, `moderation_queue`, and `player_transfers`
- add a dedicated `scout_tips` domain
- launch points first
- launch cash rewards only after legal review and anti-fraud checks are stable

This approach keeps the change set controlled while turning the existing platform into a real crowdsourced scouting network.
