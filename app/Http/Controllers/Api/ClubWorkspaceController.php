<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\ClubInternalPlayer;
use App\Models\ClubOffer;
use App\Models\ClubPromo;
use App\Models\PlayerTransfer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ClubWorkspaceController extends Controller
{
    use ApiResponds;

    public function offersIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $offers = ClubOffer::query()
            ->with(['transfer:id,negotiation_status,verification_status,counter_fee,updated_at'])
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
        if ($targetPlayerId !== null) {
            $player = User::query()->find($targetPlayerId);
            if (! $player || (string) $player->role !== 'player') {
                return $this->errorResponse('Secilen kayit bir oyuncuya ait degil.', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_player');
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

    public function promosIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $promos = ClubPromo::query()
            ->where('club_user_id', (int) $user->id)
            ->latest('id')
            ->paginate(25)
            ->through(fn (ClubPromo $promo) => $this->transformPromo($promo));

        return $this->successResponse($promos, 'Kulup tanitimlari hazir.');
    }

    public function promosStore(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $request->validate([
            'club_name' => ['required', 'string', 'min:2', 'max:120'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'video_url' => ['nullable', 'url', 'max:2000'],
            'images' => ['nullable', 'array', 'max:2'],
            'images.*' => ['string', 'max:500000'],
            'paid' => ['required', 'boolean'],
        ]);

        $promo = ClubPromo::query()->create([
            'club_user_id' => (int) $user->id,
            'club_name' => trim((string) $validated['club_name']),
            'notes' => $this->nullableString($validated['notes'] ?? null),
            'video_url' => $this->nullableString($validated['video_url'] ?? null),
            'images' => array_values($validated['images'] ?? []),
            'paid' => (bool) $validated['paid'],
        ]);

        return $this->successResponse($this->transformPromo($promo), 'Kulup tanitimi kaydedildi.', Response::HTTP_CREATED);
    }

    public function internalPlayersIndex(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $players = ClubInternalPlayer::query()
            ->where('club_user_id', (int) $user->id)
            ->latest('id')
            ->paginate(200)
            ->through(fn (ClubInternalPlayer $player) => $this->transformInternalPlayer($player));

        return $this->successResponse($players, 'Kulup ici oyuncu profilleri hazir.');
    }

    public function internalPlayersStore(Request $request): JsonResponse
    {
        $user = $this->authorizeClubUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $player = ClubInternalPlayer::query()->create($this->validatedInternalPlayerPayload($request, $user));

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

        $player->fill($this->validatedInternalPlayerPayload($request, $user));
        $player->save();

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

    private function authorizeClubUser(Request $request): User|JsonResponse
    {
        $user = $request->user();
        if (! in_array((string) $user->role, ['team', 'club'], true)) {
            return $this->errorResponse('Bu alan sadece kulup veya takim kullanicilarina aciktir.', Response::HTTP_FORBIDDEN, 'forbidden_role');
        }

        return $user;
    }

    private function validatedInternalPlayerPayload(Request $request, User $user): array
    {
        $validated = $request->validate([
            'profile_type' => ['nullable', 'string', 'max:40'],
            'visibility' => ['nullable', 'string', 'max:40'],
            'group' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'gender' => ['nullable', 'string', 'max:40'],
            'sport' => ['nullable', 'string', 'max:40'],
            'birthYear' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:120'],
            'height' => ['nullable', 'string', 'max:40'],
            'shirtNumber' => ['nullable', 'string', 'max:20'],
            'contractStatus' => ['nullable', 'string', 'max:40'],
            'contact' => ['nullable', 'string', 'max:120'],
            'dominantFoot' => ['nullable', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
            'matches' => ['nullable', 'string', 'max:20'],
            'minutes' => ['nullable', 'string', 'max:20'],
            'goals' => ['nullable', 'string', 'max:20'],
            'assists' => ['nullable', 'string', 'max:20'],
            'rating' => ['nullable', 'string', 'max:20'],
        ]);

        return [
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
            'note' => $this->nullableString($validated['note'] ?? null),
            'matches' => $this->nullableString($validated['matches'] ?? null),
            'minutes' => $this->nullableString($validated['minutes'] ?? null),
            'goals' => $this->nullableString($validated['goals'] ?? null),
            'assists' => $this->nullableString($validated['assists'] ?? null),
            'rating' => $this->nullableString($validated['rating'] ?? null),
        ];
    }

    private function transformOffer(ClubOffer $offer): array
    {
        return [
            'id' => $offer->id,
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

    private function createLinkedTransfer(User $user, array $validated, int $targetPlayerId): PlayerTransfer
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
            'to_club_id' => (int) $user->id,
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

    private function transformPromo(ClubPromo $promo): array
    {
        return [
            'id' => $promo->id,
            'club_name' => $promo->club_name,
            'notes' => $promo->notes,
            'video_url' => $promo->video_url,
            'images' => array_values($promo->images ?? []),
            'paid' => (bool) $promo->paid,
            'created_at' => optional($promo->created_at)->toIso8601String(),
        ];
    }

    private function transformInternalPlayer(ClubInternalPlayer $player): array
    {
        return [
            'id' => $player->id,
            'profile_type' => $player->profile_type,
            'visibility' => $player->visibility,
            'group' => $player->group_key,
            'name' => $player->name,
            'gender' => $player->gender,
            'sport' => $player->sport,
            'birthYear' => $player->birth_year,
            'age' => $player->age,
            'position' => $player->position,
            'height' => $player->height,
            'shirtNumber' => $player->shirt_number,
            'contractStatus' => $player->contract_status,
            'contact' => $player->contact,
            'dominantFoot' => $player->dominant_foot,
            'bio' => $player->bio,
            'note' => $player->note,
            'matches' => $player->matches,
            'minutes' => $player->minutes,
            'goals' => $player->goals,
            'assists' => $player->assists,
            'rating' => $player->rating,
            'savedAt' => optional($player->updated_at)->toIso8601String(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
