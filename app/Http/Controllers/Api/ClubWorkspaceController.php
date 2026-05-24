<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Concerns\ResolvesPublicFileUrls;
use App\Http\Controllers\Controller;
use App\Models\ClubInternalPlayer;
use App\Models\ClubOffer;
use App\Models\ClubPromo;
use App\Models\ClubTeamGroup;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ClubWorkspaceController extends Controller
{
    use ApiResponds;
    use ResolvesPublicFileUrls;

    private array $clubInternalPlayerColumnPresence = [];

    public function promosIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $promos = ClubPromo::query()
            ->where('club_user_id', (int) $user->id)
            ->latest('id')
            ->paginate(50);

        return $this->successResponse($promos, 'Kulup tanitimlari hazir.');
    }

    public function offersIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $offers = ClubOffer::query()
            ->with(['club:id,name,city', 'transfer:id,negotiation_status,verification_status,counter_fee,updated_at'])
            ->where('club_user_id', (int) $user->id)
            ->latest('id')
            ->paginate(50)
            ->through(fn (ClubOffer $offer) => $this->transformOffer($offer));

        return $this->successResponse($offers, 'Kulup teklifleri hazir.');
    }

    public function offersStore(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'target_player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'sport' => ['nullable', 'string', 'max:40'],
            'offer_type' => ['required', 'string', 'in:permanent,loan,trial,pre_contract'],
            'amount_eur' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'season' => ['nullable', 'string', 'max:20'],
            'contract_years' => ['nullable', 'integer', 'min:1', 'max:7'],
            'salary_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'signing_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'bonus_summary' => ['nullable', 'string', 'max:255'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'clauses' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $targetPlayerId = isset($validated['target_player_user_id']) ? (int) $validated['target_player_user_id'] : null;
        if ($targetPlayerId === null) {
            $targetPlayerId = User::query()
                ->where('role', 'player')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $validated['player_name']))])
                ->value('id');
            $targetPlayerId = $targetPlayerId !== null ? (int) $targetPlayerId : null;
        }
        if ($targetPlayerId !== null) {
            $player = User::query()->find($targetPlayerId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
            }
            if (! $this->playerMatchesRequestedSport($player, $validated['sport'] ?? $user->sport ?? null)) {
                return $this->errorResponse('Oyuncu secili bransla eslesmiyor.', Response::HTTP_UNPROCESSABLE_ENTITY, 'sport_mismatch');
            }
        }

        $offer = DB::transaction(function () use ($user, $validated, $targetPlayerId) {
            $transfer = $targetPlayerId ? $this->createLinkedTransfer($user, $validated, $targetPlayerId) : null;

            return ClubOffer::query()->create([
                'club_user_id' => (int) $user->id,
                'transfer_id' => $transfer?->id,
                'target_player_user_id' => $targetPlayerId,
                'player_name' => trim((string) $validated['player_name']),
                'offer_type' => trim((string) $validated['offer_type']),
                'amount_eur' => $validated['amount_eur'],
                'currency' => strtoupper(trim((string) ($validated['currency'] ?? 'EUR'))),
                'season' => $this->nullableString($validated['season'] ?? null),
                'contract_years' => $validated['contract_years'] ?? null,
                'salary_amount' => $validated['salary_amount'] ?? null,
                'signing_fee' => $validated['signing_fee'] ?? null,
                'bonus_summary' => $this->nullableString($validated['bonus_summary'] ?? null),
                'contract_start_date' => $validated['contract_start_date'] ?? null,
                'contract_end_date' => $validated['contract_end_date'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'clauses' => $this->nullableString($validated['clauses'] ?? null),
                'status' => trim((string) ($validated['status'] ?? 'sent')),
                'note' => $this->nullableString($validated['note'] ?? null),
            ]);
        });

        return $this->successResponse($this->transformOffer($offer->load('transfer')), 'Teklif kaydedildi.', Response::HTTP_CREATED);
    }

    public function managerOffersIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeManagerUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $offers = ClubOffer::query()
            ->with(['club:id,name,city', 'transfer:id,negotiation_status,verification_status,counter_fee,updated_at'])
            ->whereHas('transfer', fn ($query) => $query->where('created_by', (int) $user->id))
            ->latest('id')
            ->paginate(50)
            ->through(fn (ClubOffer $offer) => $this->transformOffer($offer));

        return $this->successResponse($offers, 'Menajer teklifleri hazir.');
    }

    public function managerOffersStore(Request $request): JsonResponse
    {
        $user = $this->authorizeManagerUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'club_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'club_name' => ['nullable', 'string', 'min:2', 'max:120'],
            'target_player_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'player_name' => ['required', 'string', 'min:2', 'max:120'],
            'sport' => ['nullable', 'string', 'max:40'],
            'offer_type' => ['required', 'string', 'in:permanent,loan,trial,pre_contract'],
            'amount_eur' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'season' => ['nullable', 'string', 'max:20'],
            'contract_years' => ['nullable', 'integer', 'min:1', 'max:7'],
            'salary_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'signing_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'bonus_summary' => ['nullable', 'string', 'max:255'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'clauses' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $club = isset($validated['club_user_id'])
            ? User::query()->find((int) $validated['club_user_id'])
            : null;
        if (! $club && ! empty($validated['club_name'])) {
            $club = User::query()
                ->whereIn('role', ['team', 'club', 'kulup'])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $validated['club_name']))])
                ->first();
        }
        if (! $club || ! in_array((string) $club->role, ['team', 'club', 'kulup'], true)) {
            return $this->errorResponse('Kulup bulunamadi. Kulup ID gir veya adi birebir eslestir.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_club');
        }

        $targetPlayerId = isset($validated['target_player_user_id']) ? (int) $validated['target_player_user_id'] : null;
        if ($targetPlayerId === null) {
            $targetPlayerId = User::query()
                ->where('role', 'player')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $validated['player_name']))])
                ->value('id');
            $targetPlayerId = $targetPlayerId !== null ? (int) $targetPlayerId : null;
        }
        if ($targetPlayerId === null) {
            return $this->errorResponse('Oyuncu bulunamadi. Oyuncu ID gir veya adi birebir eslestir.', Response::HTTP_UNPROCESSABLE_ENTITY, 'player_not_found');
        }

        $player = User::query()->find($targetPlayerId);
        if (! $player || (string) $player->role !== 'player') {
            return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
        }
        if (! $this->playerMatchesRequestedSport($player, $validated['sport'] ?? $club->sport ?? $user->sport ?? null)) {
            return $this->errorResponse('Oyuncu secili bransla eslesmiyor.', Response::HTTP_UNPROCESSABLE_ENTITY, 'sport_mismatch');
        }

        $offer = DB::transaction(function () use ($user, $validated, $targetPlayerId, $club) {
            $transfer = $this->createLinkedTransfer($user, $validated, $targetPlayerId, (int) $club->id);
            $note = $this->nullableString($validated['note'] ?? null);
            $note = trim(implode("\n", array_filter([
                $note,
                'Menajer: '.trim((string) $user->name),
            ])));

            return ClubOffer::query()->create([
                'club_user_id' => (int) $club->id,
                'transfer_id' => $transfer->id,
                'target_player_user_id' => $targetPlayerId,
                'player_name' => trim((string) $validated['player_name']),
                'offer_type' => trim((string) $validated['offer_type']),
                'amount_eur' => $validated['amount_eur'],
                'currency' => strtoupper(trim((string) ($validated['currency'] ?? 'EUR'))),
                'season' => $this->nullableString($validated['season'] ?? null),
                'contract_years' => $validated['contract_years'] ?? null,
                'salary_amount' => $validated['salary_amount'] ?? null,
                'signing_fee' => $validated['signing_fee'] ?? null,
                'bonus_summary' => $this->nullableString($validated['bonus_summary'] ?? null),
                'contract_start_date' => $validated['contract_start_date'] ?? null,
                'contract_end_date' => $validated['contract_end_date'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'clauses' => $this->nullableString($validated['clauses'] ?? null),
                'status' => trim((string) ($validated['status'] ?? 'sent')),
                'note' => $this->nullableString($note),
            ]);
        });

        return $this->successResponse($this->transformOffer($offer->load('transfer')), 'Menajer teklifi kaydedildi.', Response::HTTP_CREATED);
    }

    public function internalPlayersIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'group' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:40'],
        ]);

        $select = [
            'id',
            'profile_type',
            'visibility',
            'group_key',
            'name',
            'position',
            'birth_year',
            'age',
            'height',
            'shirt_number',
            'updated_at',
        ];

        if ($this->hasClubInternalPlayerColumn('status')) {
            $select[] = 'status';
        }

        if ($this->hasClubInternalPlayerColumn('photo_url')) {
            $select[] = 'photo_url';
        }

        if ($this->hasClubInternalPlayerColumn('sport')) {
            $select[] = 'sport';
        }

        $players = ClubInternalPlayer::query()
            ->select($select)
            ->where('club_user_id', (int) $user->id)
            ->when(! empty($validated['group']), fn ($query) => $query->where('group_key', trim((string) $validated['group'])))
            ->when(
                $this->hasClubInternalPlayerColumn('status') && ! empty($validated['status']) && $validated['status'] !== 'all',
                fn ($query) => $query->where('status', trim((string) $validated['status']))
            )
            ->when(! empty($validated['position']) && $validated['position'] !== 'all', fn ($query) => $query->where('position', trim((string) $validated['position'])))
            ->when(
                $this->hasClubInternalPlayerColumn('sport') && ! empty($validated['sport']) && $validated['sport'] !== 'all',
                fn ($query) => $query->where('sport', trim((string) $validated['sport']))
            )
            ->when(! empty($validated['search']), function ($query) use ($validated) {
                $search = '%'.trim((string) $validated['search']).'%';
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', $search)
                        ->orWhere('position', 'like', $search)
                        ->orWhere('shirt_number', 'like', $search);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (ClubInternalPlayer $player) => $this->transformInternalPlayerSummary($player))
            ->values();

        return $this->successResponse($players, 'Kulup ici oyuncu profilleri hazir.');
    }

    public function internalPlayersShow(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $player) {
            return $this->errorResponse('Kulup ici oyuncu profili bulunamadi.', Response::HTTP_NOT_FOUND, 'internal_player_not_found');
        }

        return $this->successResponse($this->transformInternalPlayer($player), 'Kulup ici oyuncu profili hazir.');
    }

    public function groupsIndex(Request $request): JsonResponse
    {
        try {
            $user = $this->authorizeClubUser($request);
            if ($user instanceof JsonResponse) {
                return $user;
            }

            if (! $this->hasUsableClubTeamGroupsTable()) {
                return $this->successResponse($this->defaultTeamGroupFallbackPayload(), 'Takim gruplari hazir.');
            }

            $groups = $this->ensureDefaultTeamGroups($user)
                ->map(fn (ClubTeamGroup $group) => $this->transformTeamGroup($group))
                ->values();

            return $this->successResponse($groups, 'Takim gruplari hazir.');
        } catch (\Throwable $e) {
            return $this->successResponse($this->defaultTeamGroupFallbackPayload(), 'Takim gruplari hazir.');
        }
    }

    public function groupsStore(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $this->ensureDefaultTeamGroups($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'group_key' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_showcased' => ['nullable', 'boolean'],
        ]);

        $groupKey = $this->normalizeTeamGroupKey($validated['group_key'] ?? $validated['name']);

        $exists = ClubTeamGroup::query()
            ->where('club_user_id', (int) $user->id)
            ->where('group_key', $groupKey)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Bu grup anahtari zaten kullaniliyor.', Response::HTTP_UNPROCESSABLE_ENTITY, 'duplicate_group_key');
        }

        $group = ClubTeamGroup::query()->create([
            'club_user_id' => (int) $user->id,
            'group_key' => $groupKey,
            'name' => trim((string) $validated['name']),
            'note' => $this->nullableString($validated['note'] ?? null),
            'is_showcased' => (bool) ($validated['is_showcased'] ?? false),
            'sort_order' => ((int) ClubTeamGroup::query()->where('club_user_id', (int) $user->id)->max('sort_order')) + 1,
        ]);

        if ($group->is_showcased) {
            $this->syncTeamGroupShowcase($user, (int) $group->id);
            $group->refresh();
        }

        return $this->successResponse($this->transformTeamGroup($group), 'Takim grubu kaydedildi.', Response::HTTP_CREATED);
    }

    public function groupsUpdate(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $group = ClubTeamGroup::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $group) {
            return $this->errorResponse('Takim grubu bulunamadi.', Response::HTTP_NOT_FOUND, 'team_group_not_found');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_showcased' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $group->name = trim((string) $validated['name']);
        }
        if (array_key_exists('note', $validated)) {
            $group->note = $this->nullableString($validated['note']);
        }
        if (array_key_exists('is_showcased', $validated)) {
            $group->is_showcased = (bool) $validated['is_showcased'];
        }

        $group->save();

        if ($group->is_showcased) {
            $this->syncTeamGroupShowcase($user, (int) $group->id);
            $group->refresh();
        }

        return $this->successResponse($this->transformTeamGroup($group), 'Takim grubu guncellendi.');
    }

    public function internalPlayersStore(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = $this->validatedInternalPlayerPayload($request, $user);
        $payload = $this->attachInternalPlayerHistory($payload, null);
        $player = ClubInternalPlayer::query()->create($payload);
        $this->syncExistingPlayerAccountDataForInternalPlayer($user, $player);

        return $this->successResponse($this->transformInternalPlayer($player), 'Kulup ici oyuncu profili kaydedildi.', Response::HTTP_CREATED);
    }

    public function internalPlayersUpdate(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $player) {
            return $this->errorResponse('Kulup ici oyuncu profili bulunamadi.', Response::HTTP_NOT_FOUND, 'internal_player_not_found');
        }

        $payload = $this->validatedInternalPlayerPayload($request, $user);
        $player->fill($this->attachInternalPlayerHistory($payload, $player));
        $player->save();
        $this->syncExistingPlayerAccountDataForInternalPlayer($user, $player);

        return $this->successResponse($this->transformInternalPlayer($player), 'Kulup ici oyuncu profili guncellendi.');
    }

    public function internalPlayersDestroy(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $player) {
            return $this->errorResponse('Kulup ici oyuncu profili bulunamadi.', Response::HTTP_NOT_FOUND, 'internal_player_not_found');
        }

        $player->delete();

        return $this->successResponse(null, 'Kulup ici oyuncu profili silindi.');
    }

    public function internalPlayersCreateAccount(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $player) {
            return $this->errorResponse('Kulup ici oyuncu profili bulunamadi.', Response::HTTP_NOT_FOUND, 'internal_player_not_found');
        }

        $teamName = $this->resolveClubLoginTeamName($user);
        $playerUser = $this->findPlayerUserForInternalPlayer($teamName, $player->name);

        if (! $playerUser) {
            $emailBase = Str::slug($player->name ?: 'player', '.');
            $email = sprintf('%s.%s@nextscout.local', $emailBase ?: 'player', Str::lower(Str::random(8)));

            $playerUser = User::query()->create([
                'name' => $player->name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'role' => 'player',
                'is_verified' => true,
                'email_verified_at' => now(),
                'player_password_initialized' => false,
            ]);
        }

        $this->syncPlayerAccountFromInternalPlayer($playerUser, $user, $player, $teamName);

        if (! (bool) $playerUser->player_password_initialized) {
            $playerUser->forceFill(['player_password_initialized' => false])->save();
        }

        return $this->successResponse([
            'player_id' => (int) $player->id,
            'player_user_id' => (int) $playerUser->id,
            'team_name' => $teamName,
            'player_name' => $playerUser->name,
            'account_enabled' => true,
            'player_password_initialized' => (bool) $playerUser->player_password_initialized,
        ], 'Oyuncu giris hesabi hazirlandi.');
    }

    public function internalPlayersResetPasswordSetup(int $id, Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->find($id);

        if (! $player) {
            return $this->errorResponse('Kulup ici oyuncu profili bulunamadi.', Response::HTTP_NOT_FOUND, 'internal_player_not_found');
        }

        $teamName = $this->resolveClubLoginTeamName($user);
        $playerUser = $this->findPlayerUserForInternalPlayer($teamName, $player->name);

        if (! $playerUser) {
            return $this->errorResponse('Bu oyuncu icin acilmis bir giris hesabi bulunamadi.', Response::HTTP_NOT_FOUND, 'player_account_not_found');
        }

        $playerUser->forceFill([
            'player_password_initialized' => false,
        ])->save();

        return $this->successResponse([
            'player_id' => (int) $player->id,
            'player_user_id' => (int) $playerUser->id,
            'team_name' => $teamName,
            'player_name' => $playerUser->name,
            'account_enabled' => true,
            'player_password_initialized' => false,
        ], 'Oyuncu ilk sifre olusturma akisi tekrar acildi.');
    }

    private function authorizeClubUser(Request $request): User|JsonResponse
    {
        $user = $request->user();
        if (! in_array((string) $user->role, ['team', 'club', 'kulup'], true)) {
            return $this->errorResponse('Bu alan sadece kulup veya takim kullanicilarina aciktir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        return $user;
    }

    private function authorizeManagerUser(Request $request): User|JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array((string) $user->role, ['manager', 'menajer'], true)) {
            return $this->errorResponse('Bu alan sadece menajer hesaplari icin aciktir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        return $user;
    }

    private function validatedInternalPlayerPayload(Request $request, User $user): array
    {
        $validated = $request->validate([
            'profile_type' => ['nullable', 'string', 'max:40'],
            'visibility' => ['nullable', 'string', 'max:40'],
            'group' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'in:active,trial,injured,development,departed,archived'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'gender' => ['nullable', 'string', 'max:40'],
            'sport' => ['nullable', 'string', 'max:40'],
            'birthYear' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:120'],
            'height' => ['nullable', 'string', 'max:40'],
            'shirtNumber' => ['nullable', 'string', 'max:20'],
            'photoUrl' => ['nullable', 'string', 'max:2048'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'contractStatus' => ['nullable', 'string', 'max:40'],
            'contact' => ['nullable', 'string', 'max:120'],
            'dominantFoot' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'coachNote' => ['nullable', 'string', 'max:4000'],
            'managerNote' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
            'matches' => ['nullable', 'string', 'max:20'],
            'minutes' => ['nullable', 'string', 'max:20'],
            'goals' => ['nullable', 'string', 'max:20'],
            'assists' => ['nullable', 'string', 'max:20'],
            'rating' => ['nullable', 'string', 'max:20'],
            'aggregateHighlights' => ['nullable', 'array'],
            'aggregate_highlights' => ['nullable', 'array'],
            'lastMatchHighlights' => ['nullable', 'array'],
            'last_match_highlights' => ['nullable', 'array'],
            'lastMatchRating' => ['nullable', 'string', 'max:20'],
            'last_match_rating' => ['nullable', 'string', 'max:20'],
            'lastMatchSummary' => ['nullable', 'string', 'max:1000'],
            'last_match_summary' => ['nullable', 'string', 'max:1000'],
            'lastMatchDate' => ['nullable', 'date'],
            'last_match_date' => ['nullable', 'date'],
            'performanceMatchName' => ['nullable', 'string', 'max:160'],
            'performanceMatchDate' => ['nullable', 'date'],
            'performanceSummary' => ['nullable', 'string', 'max:1000'],
            'manualEventType' => ['nullable', 'string', 'max:40'],
            'manualEventAction' => ['nullable', 'string', 'in:create,update,delete'],
            'manualEventDate' => ['nullable', 'date'],
            'manualEventId' => ['nullable', 'string', 'max:80'],
            'manualEventTitle' => ['nullable', 'string', 'max:120'],
            'manualEventDetails' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            'club_user_id' => (int) $user->id,
            'profile_type' => trim((string) ($validated['profile_type'] ?? 'internal_profile')),
            'visibility' => trim((string) ($validated['visibility'] ?? 'club_only')),
            'group_key' => trim((string) $validated['group']),
            'name' => trim((string) $validated['name']),
            'gender' => $this->nullableString($validated['gender'] ?? null),
            'sport' => $this->nullableString($validated['sport'] ?? null),
            'birth_year' => $this->nullableString($validated['birthYear'] ?? null),
            'age' => $this->nullableString($validated['age'] ?? null),
            'position' => $this->nullableString($validated['position'] ?? null),
            'height' => $this->nullableString($validated['height'] ?? null),
            'shirt_number' => $this->nullableString($validated['shirtNumber'] ?? null),
            'contract_status' => $this->nullableString($validated['contractStatus'] ?? null),
            'contact' => $this->nullableString($validated['contact'] ?? null),
            'dominant_foot' => $this->nullableString($validated['dominantFoot'] ?? null),
            'bio' => $this->nullableString($validated['bio'] ?? null),
            'note' => $this->nullableString(($validated['managerNote'] ?? null) ?: ($validated['note'] ?? null)),
            'matches' => $this->nullableString($validated['matches'] ?? null),
            'minutes' => $this->nullableString($validated['minutes'] ?? null),
            'goals' => $this->nullableString($validated['goals'] ?? null),
            'assists' => $this->nullableString($validated['assists'] ?? null),
            'rating' => $this->nullableString($validated['rating'] ?? null),
            'aggregate_highlights' => $this->normalizeStatHighlights(
                $validated['aggregateHighlights'] ?? $validated['aggregate_highlights'] ?? null
            ),
            'last_match_highlights' => $this->normalizeStatHighlights(
                $validated['lastMatchHighlights'] ?? $validated['last_match_highlights'] ?? null
            ),
            'last_match_rating' => $this->nullableString(
                $validated['lastMatchRating'] ?? $validated['last_match_rating'] ?? null
            ),
            'last_match_summary' => $this->nullableString(
                $validated['lastMatchSummary'] ?? $validated['last_match_summary'] ?? null
            ),
            'last_match_date' => ! empty($validated['lastMatchDate'] ?? $validated['last_match_date'] ?? null)
                ? Carbon::parse($validated['lastMatchDate'] ?? $validated['last_match_date'])->toIso8601String()
                : null,
            'performance_match_name' => $this->nullableString($validated['performanceMatchName'] ?? null),
            'performance_match_date' => ! empty($validated['performanceMatchDate']) ? Carbon::parse($validated['performanceMatchDate'])->startOfDay()->toIso8601String() : null,
            'performance_summary' => $this->nullableString($validated['performanceSummary'] ?? null),
            'manual_event_type' => $this->nullableString($validated['manualEventType'] ?? null),
            'manual_event_action' => $this->nullableString($validated['manualEventAction'] ?? null),
            'manual_event_date' => ! empty($validated['manualEventDate']) ? Carbon::parse($validated['manualEventDate'])->startOfDay()->toIso8601String() : null,
            'manual_event_id' => $this->nullableString($validated['manualEventId'] ?? null),
            'manual_event_title' => $this->nullableString($validated['manualEventTitle'] ?? null),
            'manual_event_details' => $this->nullableString($validated['manualEventDetails'] ?? null),
        ];

        if ($this->hasClubInternalPlayerColumn('status')) {
            $payload['status'] = trim((string) ($validated['status'] ?? 'active'));
        }

        if ($this->hasClubInternalPlayerColumn('coach_note')) {
            $payload['coach_note'] = $this->nullableString($validated['coachNote'] ?? null);
        }

        if ($this->hasClubInternalPlayerColumn('manager_note')) {
            $payload['manager_note'] = $this->nullableString($validated['managerNote'] ?? null);
        }

        if ($this->hasClubInternalPlayerPhotoUrlColumn()) {
            $payload['photo_url'] = $this->nullableString($validated['photoUrl'] ?? null);
            if ($request->hasFile('photo')) {
                $payload['photo_url'] = $request->file('photo')->store('club-internal-player-photos', 'public');
            }
        }

        return $payload;
    }

    private function attachInternalPlayerHistory(array $payload, ?ClubInternalPlayer $existingPlayer): array
    {
        $now = now()->toIso8601String();
        $noteHistory = $existingPlayer?->note_history ?? [];
        $performanceHistory = $existingPlayer?->performance_history ?? [];
        $timelineEvents = $this->normalizeInternalPlayerTimelineEvents($existingPlayer?->timeline_events ?? []);

        if ($existingPlayer === null) {
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'profile_created',
                'Kulup profili olusturuldu',
                $now,
                ($payload['group_key'] ?? '-').' grubuna ilk kayit acildi.'
            );
        }

        if ($existingPlayer !== null && ($existingPlayer->group_key !== ($payload['group_key'] ?? null))) {
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'group_changed',
                'Takim grubu guncellendi',
                $now,
                ($existingPlayer->group_key ?: '-').' -> '.($payload['group_key'] ?? '-')
            );
        }

        if ($existingPlayer !== null && ($existingPlayer->status !== ($payload['status'] ?? null))) {
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'status_changed',
                'Oyuncu durumu degisti',
                $now,
                ($existingPlayer->status ?: 'active').' -> '.($payload['status'] ?? 'active')
            );
        }

        if ($existingPlayer !== null && ($existingPlayer->contract_status !== ($payload['contract_status'] ?? null))) {
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'contract_changed',
                'Sozlesme durumu guncellendi',
                $now,
                ($existingPlayer->contract_status ?: '-').' -> '.($payload['contract_status'] ?? '-')
            );
        }

        $newCoachNote = $payload['coach_note'] ?? null;
        $oldCoachNote = $existingPlayer?->coach_note;
        if ($newCoachNote && $newCoachNote !== $oldCoachNote) {
            array_unshift($noteHistory, [
                'text' => $newCoachNote,
                'role' => 'coach',
                'created_at' => $now,
            ]);
            $noteHistory = array_slice($noteHistory, 0, 12);
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'note_added',
                'Antrenor notu guncellendi',
                $now,
                $newCoachNote
            );
        }

        $newManagerNote = $payload['manager_note'] ?? null;
        $oldManagerNote = $existingPlayer?->manager_note ?: $existingPlayer?->note;
        if ($newManagerNote && $newManagerNote !== $oldManagerNote) {
            array_unshift($noteHistory, [
                'text' => $newManagerNote,
                'role' => 'manager',
                'created_at' => $now,
            ]);
            $noteHistory = array_slice($noteHistory, 0, 12);
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'note_added',
                'Yonetici notu guncellendi',
                $now,
                $newManagerNote
            );
        }

        $hasPerformanceData = collect(['matches', 'minutes', 'goals', 'assists', 'rating'])
            ->contains(fn (string $field) => ! empty($payload[$field]));
        $performanceChanged = $existingPlayer === null
            || $existingPlayer->matches !== ($payload['matches'] ?? null)
            || $existingPlayer->minutes !== ($payload['minutes'] ?? null)
            || $existingPlayer->goals !== ($payload['goals'] ?? null)
            || $existingPlayer->assists !== ($payload['assists'] ?? null)
            || $existingPlayer->rating !== ($payload['rating'] ?? null);

        if ($hasPerformanceData && $performanceChanged) {
            array_unshift($performanceHistory, [
                'match_name' => $payload['performance_match_name'] ?? $payload['last_match_summary'] ?? null,
                'match_date' => $payload['performance_match_date'] ?? $payload['last_match_date'] ?? null,
                'matches' => $payload['matches'] ?? null,
                'minutes' => $payload['minutes'] ?? null,
                'goals' => $payload['goals'] ?? null,
                'assists' => $payload['assists'] ?? null,
                'rating' => $payload['last_match_rating'] ?? $payload['rating'] ?? null,
                'summary' => $payload['performance_summary'] ?? $payload['last_match_summary'] ?? null,
                'highlights' => array_values($payload['last_match_highlights'] ?? []),
                'created_at' => $now,
            ]);
            $performanceHistory = array_slice($performanceHistory, 0, 12);
            $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                'performance_updated',
                ($payload['performance_match_name'] ?? $payload['last_match_summary']) ?: 'Performans ozeti guncellendi',
                ($payload['performance_match_date'] ?? $payload['last_match_date']) ?: $now,
                trim(implode(' | ', array_filter([
                    $payload['performance_summary'] ?? $payload['last_match_summary'] ?? null,
                    sprintf('%s dakika', $payload['minutes'] ?? '0'),
                    sprintf('rating %s', $payload['last_match_rating'] ?? $payload['rating'] ?? '-'),
                ])))
            );
        }

        $manualEventId = $payload['manual_event_id'] ?? null;
        $manualEventAction = $payload['manual_event_action'] ?? 'create';
        if ($manualEventId && $manualEventAction === 'delete') {
            $timelineEvents = array_values(array_filter(
                $timelineEvents,
                fn (array $item) => (string) ($item['id'] ?? '') !== (string) $manualEventId
            ));
        } elseif ($manualEventId && $manualEventAction === 'update') {
            $timelineEvents = array_map(function (array $item) use ($payload, $manualEventId, $now) {
                if ((string) ($item['id'] ?? '') !== (string) $manualEventId) {
                    return $item;
                }

                $item['type'] = $payload['manual_event_type'] ?: ($item['type'] ?? 'manual_note');
                $item['title'] = $payload['manual_event_title'] ?: ($item['title'] ?? 'Guncelleme');
                $item['details'] = $payload['manual_event_details'] ?? ($item['details'] ?? null);
                $item['created_at'] = $payload['manual_event_date'] ?: ($item['created_at'] ?? $now);
                $item['is_manual'] = true;

                return $item;
            }, $timelineEvents);
        }

        if (! empty($payload['manual_event_title'])) {
            if (! $manualEventId || $manualEventAction === 'create') {
                $timelineEvents[] = $this->makeInternalPlayerTimelineEvent(
                    $payload['manual_event_type'] ?: 'manual_note',
                    $payload['manual_event_title'],
                    $payload['manual_event_date'] ?: $now,
                    $payload['manual_event_details'] ?? null,
                    true
                );
            }
        }

        if ($this->hasClubInternalPlayerColumn('note_history')) {
            $payload['note_history'] = array_values($noteHistory);
        }

        if ($this->hasClubInternalPlayerColumn('performance_history')) {
            $payload['performance_history'] = array_values($performanceHistory);
        }

        if ($this->hasClubInternalPlayerColumn('timeline_events')) {
            $payload['timeline_events'] = array_values(array_slice($timelineEvents, -20));
        }

        unset($payload['performance_match_name'], $payload['performance_match_date'], $payload['performance_summary'], $payload['manual_event_type'], $payload['manual_event_action'], $payload['manual_event_date'], $payload['manual_event_id'], $payload['manual_event_title'], $payload['manual_event_details']);

        return $payload;
    }

    private function makeInternalPlayerTimelineEvent(string $type, string $title, string $createdAt, ?string $details = null, bool $isManual = false): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'title' => $title,
            'details' => $this->nullableString($details),
            'created_at' => $createdAt,
            'is_manual' => $isManual,
        ];
    }

    private function normalizeInternalPlayerTimelineEvents(array $events): array
    {
        $automaticTypes = [
            'profile_created',
            'group_changed',
            'status_changed',
            'contract_changed',
            'note_added',
            'performance_updated',
        ];

        return array_values(array_map(function ($item) use ($automaticTypes) {
            $event = is_array($item) ? $item : [];
            $type = trim((string) ($event['type'] ?? 'manual_note'));
            $title = trim((string) ($event['title'] ?? 'Guncelleme'));
            $details = $this->nullableString($event['details'] ?? null);
            $createdAt = trim((string) ($event['created_at'] ?? now()->toIso8601String()));
            $normalizedId = trim((string) ($event['id'] ?? ''));

            if ($normalizedId === '') {
                $normalizedId = 'legacy-'.md5($type.'|'.$title.'|'.$details.'|'.$createdAt);
            }

            $isManual = array_key_exists('is_manual', $event)
                ? (bool) $event['is_manual']
                : ! in_array($type, $automaticTypes, true);

            return [
                'id' => $normalizedId,
                'type' => $type === '' ? 'manual_note' : $type,
                'title' => $title === '' ? 'Guncelleme' : $title,
                'details' => $details,
                'created_at' => $createdAt,
                'is_manual' => $isManual,
            ];
        }, $events));
    }

    private function transformOffer(ClubOffer $offer): array
    {
        return [
            'id' => $offer->id,
            'club_user_id' => $offer->club_user_id,
            'club_name' => $offer->club?->name,
            'club_city' => $offer->club?->city,
            'transfer_id' => $offer->transfer_id,
            'target_player_user_id' => $offer->target_player_user_id,
            'player_name' => $offer->player_name,
            'offer_type' => $offer->offer_type,
            'amount_eur' => (float) $offer->amount_eur,
            'currency' => $offer->currency ?: 'EUR',
            'season' => $offer->season,
            'contract_years' => $offer->contract_years,
            'salary_amount' => $offer->salary_amount !== null ? (float) $offer->salary_amount : null,
            'signing_fee' => $offer->signing_fee !== null ? (float) $offer->signing_fee : null,
            'bonus_summary' => $offer->bonus_summary,
            'contract_start_date' => optional($offer->contract_start_date)->toDateString(),
            'contract_end_date' => optional($offer->contract_end_date)->toDateString(),
            'expires_at' => optional($offer->expires_at)->toIso8601String(),
            'clauses' => $offer->clauses,
            'status' => $offer->status,
            'note' => $offer->note,
            'negotiation_status' => $offer->transfer?->negotiation_status,
            'verification_status' => $offer->transfer?->verification_status,
            'counter_fee' => $offer->transfer?->counter_fee !== null ? (float) $offer->transfer->counter_fee : null,
            'created_at' => optional($offer->created_at)->toIso8601String(),
        ];
    }

    private function createLinkedTransfer(User $user, array $validated, int $targetPlayerId, ?int $toClubId = null): PlayerTransfer
    {
        $transferDate = Carbon::now();
        $month = (int) $transferDate->format('n');
        $seasonStart = $month >= 7 ? (int) $transferDate->format('Y') : ((int) $transferDate->format('Y') - 1);
        $season = sprintf('%d-%d', $seasonStart, ($seasonStart + 1) % 100);
        $window = in_array($month, [1, 2], true) ? 'winter' : 'summer';
        $note = $this->nullableString($validated['note'] ?? null);
        $offerType = trim((string) ($validated['offer_type'] ?? 'permanent'));
        $currency = strtoupper(trim((string) ($validated['currency'] ?? 'EUR')));
        $season = $this->nullableString($validated['season'] ?? null) ?: $season;
        $contractEndDate = $validated['contract_end_date'] ?? null;
        $summaryLines = array_filter([
            $note ? 'Kulup teklifi notu: '.$note : null,
            !empty($validated['salary_amount']) ? 'Yillik maas: '.$validated['salary_amount'].' '.$currency : null,
            !empty($validated['signing_fee']) ? 'Imza parasi: '.$validated['signing_fee'].' '.$currency : null,
            !empty($validated['bonus_summary']) ? 'Bonus: '.trim((string) $validated['bonus_summary']) : null,
            !empty($validated['clauses']) ? 'Ozel maddeler: '.trim((string) $validated['clauses']) : null,
            !empty($validated['expires_at']) ? 'Son cevap tarihi: '.Carbon::parse($validated['expires_at'])->toDateString() : null,
        ]);

        return PlayerTransfer::query()->create([
            'player_id' => $targetPlayerId,
            'from_club_id' => null,
            'to_club_id' => $toClubId ?? (int) $user->id,
            'fee' => $validated['amount_eur'],
            'currency' => $currency,
            'transfer_date' => $transferDate->toDateString(),
            'transfer_type' => $offerType === 'trial' ? 'unknown' : ($offerType === 'pre_contract' ? 'unknown' : $offerType),
            'contract_until' => $contractEndDate,
            'season' => $season,
            'window' => $window,
            'source_url' => null,
            'confidence_score' => 0.9,
            'verification_status' => 'pending',
            'negotiation_status' => 'open',
            'notes' => $summaryLines ? implode("\n", $summaryLines) : 'Kulup panelinden teklif olusturuldu.',
            'negotiation_notes' => $note,
            'created_by' => (int) $user->id,
            'verified_by' => null,
            'verified_at' => null,
            'negotiation_updated_by' => (int) $user->id,
            'negotiation_updated_at' => $transferDate,
        ]);
    }

    private function transformInternalPlayer(ClubInternalPlayer $player): array
    {
        $timelineEvents = $this->normalizeInternalPlayerTimelineEvents($player->timeline_events ?? []);

        return [
            'id' => $player->id,
            'profile_type' => $player->profile_type,
            'visibility' => $player->visibility,
            'group' => $player->group_key,
            'status' => $player->status ?: 'active',
            'name' => $player->name,
            'gender' => $player->gender,
            'sport' => $player->sport,
            'birthYear' => $player->birth_year,
            'age' => $player->age,
            'position' => $player->position,
            'height' => $player->height,
            'shirtNumber' => $player->shirt_number,
            'photoUrl' => $this->hasClubInternalPlayerPhotoUrlColumn()
                ? $this->publicFileUrl($player->photo_url)
                : null,
            'contractStatus' => $player->contract_status,
            'contact' => $player->contact,
            'dominantFoot' => $player->dominant_foot,
            'bio' => $player->bio,
            'coachNote' => $player->coach_note,
            'managerNote' => $player->manager_note ?: $player->note,
            'note' => $player->manager_note ?: $player->note,
            'noteHistory' => array_values(array_map(function ($item) {
                if (! is_array($item)) {
                    return [
                        'text' => (string) $item,
                        'role' => 'manager',
                        'created_at' => now()->toIso8601String(),
                    ];
                }

                return [
                    'text' => $item['text'] ?? '',
                    'role' => $item['role'] ?? 'manager',
                    'created_at' => $item['created_at'] ?? now()->toIso8601String(),
                ];
            }, $player->note_history ?? [])),
            'matches' => $player->matches,
            'minutes' => $player->minutes,
            'goals' => $player->goals,
            'assists' => $player->assists,
            'rating' => $player->rating,
            'aggregateHighlights' => array_values($player->aggregate_highlights ?? []),
            'performanceHistory' => array_values($player->performance_history ?? []),
            'lastMatchRating' => $player->last_match_rating,
            'lastMatchSummary' => $player->last_match_summary,
            'lastMatchDate' => $player->last_match_date,
            'lastMatchHighlights' => array_values($player->last_match_highlights ?? []),
            'timelineEvents' => $timelineEvents,
            'accountEnabled' => false,
            'playerUserId' => null,
            'playerPasswordInitialized' => false,
            'loginTeamName' => null,
            'savedAt' => optional($player->updated_at)->toIso8601String(),
        ];
    }

    private function transformInternalPlayerSummary(ClubInternalPlayer $player): array
    {
        $performanceHistory = array_values($player->performance_history ?? []);
        $lastPerformance = is_array($performanceHistory[0] ?? null) ? $performanceHistory[0] : null;
        $lastHighlights = array_values($player->last_match_highlights ?? []);
        if ($lastHighlights === []) {
            $lastHighlights = is_array($lastPerformance['highlights'] ?? null)
                ? array_values($lastPerformance['highlights'])
                : [];
        }

        return [
            'id' => $player->id,
            'profile_type' => $player->profile_type,
            'visibility' => $player->visibility,
            'group' => $player->group_key,
            'status' => $player->status ?: 'active',
            'name' => $player->name,
            'position' => $player->position,
            'birthYear' => $player->birth_year,
            'age' => $player->age,
            'height' => $player->height,
            'shirtNumber' => $player->shirt_number,
            'photoUrl' => $this->hasClubInternalPlayerPhotoUrlColumn()
                ? $this->publicFileUrl($player->photo_url)
                : null,
            'matches' => $player->matches,
            'minutes' => $player->minutes,
            'goals' => $player->goals,
            'assists' => $player->assists,
            'rating' => $player->rating,
            'aggregateHighlights' => array_values($player->aggregate_highlights ?? []),
            'performanceHistory' => $performanceHistory,
            'lastMatchRating' => $player->last_match_rating ?? ($lastPerformance['rating'] ?? null),
            'lastMatchSummary' => $player->last_match_summary ?? ($lastPerformance['summary'] ?? null),
            'lastMatchDate' => $player->last_match_date ?? ($lastPerformance['match_date'] ?? null),
            'lastMatchHighlights' => $lastHighlights,
            'savedAt' => optional($player->updated_at)->toIso8601String(),
        ];
    }

    private function normalizeStatHighlights(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(function ($item): array {
            if (! is_array($item)) {
                return [
                    'label' => '',
                    'value' => '',
                ];
            }

            return [
                'label' => trim((string) ($item['label'] ?? '')),
                'value' => trim((string) ($item['value'] ?? '')),
            ];
        }, array_filter($value, fn ($item) => is_array($item))));
    }

    private function resolveClubLoginTeamName(User $user): string
    {
        $user->loadMissing('teamProfile');

        $teamName = trim((string) ($user->teamProfile?->team_name ?? $user->name ?? ''));

        return $teamName !== '' ? $teamName : 'Kulup Takimi';
    }

    private function findPlayerUserForInternalPlayer(string $teamName, string $playerName): ?User
    {
        $exactMatch = User::query()
            ->select('users.*')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->whereRaw('LOWER(TRIM(users.name)) = ?', [Str::of($playerName)->trim()->lower()->value()])
            ->whereRaw('LOWER(TRIM(player_profiles.current_team)) = ?', [Str::of($teamName)->trim()->lower()->value()])
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        return User::query()
            ->select('users.*', 'player_profiles.current_team as login_current_team')
            ->join('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'player')
            ->get()
            ->first(function (User $user) use ($teamName, $playerName): bool {
                $currentTeam = (string) ($user->getAttribute('login_current_team') ?? '');

                return $this->lookupValuesMatch((string) $user->name, $playerName)
                    && $this->lookupValuesMatch($currentTeam, $teamName);
            });
    }

    private function syncExistingPlayerAccountDataForInternalPlayer(User $clubUser, ClubInternalPlayer $internalPlayer): void
    {
        $teamName = $this->resolveClubLoginTeamName($clubUser);
        $playerUser = $this->findPlayerUserForInternalPlayer($teamName, (string) $internalPlayer->name);

        if (! $playerUser) {
            return;
        }

        $this->syncPlayerAccountFromInternalPlayer($playerUser, $clubUser, $internalPlayer, $teamName);
    }

    private function syncPlayerAccountFromInternalPlayer(User $playerUser, User $clubUser, ClubInternalPlayer $internalPlayer, string $teamName): void
    {
        $birthYear = $this->toNullableInt($internalPlayer->birth_year);
        $heightCm = $this->toNullableInt($internalPlayer->height);
        $age = $this->resolveInternalPlayerAge($internalPlayer);
        $rating = $this->toNullableDecimal($internalPlayer->rating);

        $playerUser->forceFill(array_filter([
            'name' => $internalPlayer->name ?: $playerUser->name,
            'sport' => $internalPlayer->sport ?: $playerUser->sport,
            'position' => $internalPlayer->position ?: $playerUser->position,
            'age' => $age,
            'rating' => $rating,
        ], static fn ($value) => $value !== null))->save();

        PlayerProfile::query()->updateOrCreate(
            ['user_id' => (int) $playerUser->id],
            [
                'birth_year' => $birthYear,
                'position' => $internalPlayer->position,
                'dominant_foot' => $internalPlayer->dominant_foot,
                'height_cm' => $heightCm,
                'current_team' => $teamName,
            ]
        );

        PlayerStatistic::query()->updateOrCreate(
            [
                'user_id' => (int) $playerUser->id,
                'club_id' => (int) $clubUser->id,
                'season' => $this->resolveCurrentSeasonLabel(),
            ],
            [
                'league' => $teamName,
                'matches_played' => $this->toStatInt($internalPlayer->matches),
                'matches_started' => 0,
                'matches_benched' => 0,
                'goals' => $this->toStatInt($internalPlayer->goals),
                'assists' => $this->toStatInt($internalPlayer->assists),
                'yellow_cards' => 0,
                'red_cards' => 0,
                'minutes_played' => $this->toStatInt($internalPlayer->minutes),
                'avg_rating' => $rating,
                'metadata' => array_filter([
                    'source' => 'club_internal_player',
                    'club_internal_player_id' => (int) $internalPlayer->id,
                    'group_key' => $internalPlayer->group_key,
                    'status' => $internalPlayer->status,
                ], static fn ($value) => $value !== null && $value !== ''),
            ]
        );
    }

    private function normalizeLookupValue(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->squish()
            ->ascii('tr')
            ->lower()
            ->value();
    }

    private function normalizeCompactLookupValue(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', $this->normalizeLookupValue($value));
    }

    private function lookupValuesMatch(string $left, string $right): bool
    {
        $normalizedLeft = $this->normalizeLookupValue($left);
        $normalizedRight = $this->normalizeLookupValue($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        return $this->normalizeCompactLookupValue($normalizedLeft) === $this->normalizeCompactLookupValue($normalizedRight);
    }

    private function ensureDefaultTeamGroups(User $user)
    {
        $existing = ClubTeamGroup::query()
            ->where('club_user_id', (int) $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        foreach ($this->defaultTeamGroups() as $index => $group) {
            ClubTeamGroup::query()->create([
                'club_user_id' => (int) $user->id,
                'group_key' => $group['group_key'],
                'name' => $group['name'],
                'note' => $group['note'],
                'is_showcased' => false,
                'sort_order' => $index + 1,
            ]);
        }

        return ClubTeamGroup::query()
            ->where('club_user_id', (int) $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function defaultTeamGroups(): array
    {
        return [
            ['group_key' => 'u9', 'name' => 'U9', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u10', 'name' => 'U10', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u11', 'name' => 'U11', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u12', 'name' => 'U12', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u13', 'name' => 'U13', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u14', 'name' => 'U14', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u15', 'name' => 'U15', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u16', 'name' => 'U16', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u17', 'name' => 'U17', 'note' => 'Temel yas grubu kayit alani.'],
            ['group_key' => 'u19', 'name' => 'U19', 'note' => 'Gecis yas grubu kayit alani.'],
            ['group_key' => 'a-team', 'name' => 'A Takim', 'note' => 'Kulubun vitrin ve ana takim havuzu.'],
        ];
    }

    private function defaultTeamGroupFallbackPayload(): array
    {
        return array_map(
            fn (array $group, int $index) => [
                'id' => $index + 1,
                'group_key' => $group['group_key'],
                'name' => $group['name'],
                'note' => $group['note'],
                'is_showcased' => false,
                'sort_order' => $index + 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            $this->defaultTeamGroups(),
            array_keys($this->defaultTeamGroups())
        );
    }

    private function hasUsableClubTeamGroupsTable(): bool
    {
        if (! Schema::hasTable('club_team_groups')) {
            return false;
        }

        foreach (['club_user_id', 'group_key', 'name', 'note', 'is_showcased', 'sort_order'] as $column) {
            if (! Schema::hasColumn('club_team_groups', $column)) {
                return false;
            }
        }

        return true;
    }

    private function transformTeamGroup(ClubTeamGroup $group): array
    {
        $userSport = User::query()
            ->whereKey($group->club_user_id)
            ->value('sport');
        $groupSport = ClubInternalPlayer::query()
            ->where('club_user_id', $group->club_user_id)
            ->where('group_key', $group->group_key)
            ->whereNotNull('sport')
            ->where('sport', '!=', '')
            ->value('sport');

        return [
            'id' => $group->id,
            'group_key' => $group->group_key,
            'name' => $group->name,
            'note' => $group->note,
            'sport' => $this->normalizeSportValue($groupSport ?? $userSport),
            'is_showcased' => (bool) $group->is_showcased,
            'sort_order' => (int) $group->sort_order,
            'created_at' => optional($group->created_at)->toIso8601String(),
            'updated_at' => optional($group->updated_at)->toIso8601String(),
        ];
    }

    private function normalizeTeamGroupKey(?string $value): string
    {
        $normalized = Str::of((string) $value)
            ->trim()
            ->lower()
            ->slug('-')
            ->limit(40, '')
            ->value();

        if ($normalized === '') {
            return 'group-'.Str::lower(Str::random(6));
        }

        return $normalized;
    }

    private function syncTeamGroupShowcase(User $user, int $activeGroupId): void
    {
        ClubTeamGroup::query()
            ->where('club_user_id', (int) $user->id)
            ->where('id', '!=', $activeGroupId)
            ->where('is_showcased', true)
            ->update(['is_showcased' => false]);
    }

    private function playerMatchesRequestedSport(User $player, mixed $requestedSport): bool
    {
        $requested = $this->normalizeSportValue($requestedSport);
        if ($requested === null) {
            return true;
        }

        $profile = $player->relationLoaded('playerProfile')
            ? $player->playerProfile
            : PlayerProfile::query()->where('user_id', (int) $player->id)->first();

        $candidates = [
            $this->normalizeSportValue($player->sport),
            $this->inferSportFromPosition($player->position ?? null),
            $this->inferSportFromPosition($profile?->position),
        ];

        return in_array($requested, array_filter($candidates), true);
    }

    private function normalizeSportValue(mixed $value): ?string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->trim()
            ->lower()
            ->ascii()
            ->value();

        return match ($normalized) {
            '', 'all', 'tum', 'coklu spor' => null,
            'football', 'futbol' => 'futbol',
            'basketball', 'basketbol' => 'basketbol',
            'volleyball', 'voleybol', 'voleyball' => 'voleybol',
            default => $normalized,
        };
    }

    private function inferSportFromPosition(mixed $value): ?string
    {
        $position = Str::of((string) ($value ?? ''))
            ->trim()
            ->lower()
            ->ascii()
            ->value();

        if ($position === '') {
            return null;
        }

        $footballPositions = [
            'gk',
            'kaleci',
            'defans',
            'bek',
            'stoper',
            'orta saha',
            'orta-saha',
            'on libero',
            'kanat',
            'forvet',
            'santrfor',
            'striker',
            'winger',
            'midfielder',
            'defender',
            'goalkeeper',
        ];

        $basketballPositions = ['pg', 'sg', 'sf', 'pf', 'c', 'guard', 'forward', 'center', 'pivot'];
        $volleyballPositions = [
            'pasor',
            'smacor',
            'orta oyuncu',
            'libero',
            'pasor caprazi',
            'opposite',
            'setter',
            'spiker',
        ];

        foreach ($footballPositions as $needle) {
            if (str_contains($position, $needle)) {
                return 'futbol';
            }
        }
        foreach ($basketballPositions as $needle) {
            if ($position === $needle || (strlen($needle) > 2 && str_contains($position, $needle))) {
                return 'basketbol';
            }
        }
        foreach ($volleyballPositions as $needle) {
            if (str_contains($position, $needle)) {
                return 'voleybol';
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function toStatInt(mixed $value): int
    {
        return max(0, $this->toNullableInt($value) ?? 0);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9\-]+/', '', trim((string) $value));

        if ($normalized === null || $normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function toNullableDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function resolveInternalPlayerAge(ClubInternalPlayer $internalPlayer): ?int
    {
        $age = $this->toNullableInt($internalPlayer->age);
        if ($age !== null) {
            return max(0, $age);
        }

        $birthYear = $this->toNullableInt($internalPlayer->birth_year);

        if ($birthYear === null) {
            return null;
        }

        return max(0, ((int) now()->format('Y')) - $birthYear);
    }

    private function resolveCurrentSeasonLabel(): string
    {
        $today = now();
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');
        $seasonStart = $month >= 7 ? $year : $year - 1;

        return sprintf('%d-%02d', $seasonStart, ($seasonStart + 1) % 100);
    }

    private function hasClubInternalPlayerPhotoUrlColumn(): bool
    {
        return $this->hasClubInternalPlayerColumn('photo_url');
    }

    private function hasClubInternalPlayerColumn(string $column): bool
    {
        if (array_key_exists($column, $this->clubInternalPlayerColumnPresence)) {
            return $this->clubInternalPlayerColumnPresence[$column];
        }

        return $this->clubInternalPlayerColumnPresence[$column] = Schema::hasColumn('club_internal_players', $column);
    }
}
