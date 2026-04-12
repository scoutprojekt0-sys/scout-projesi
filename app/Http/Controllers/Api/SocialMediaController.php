<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnforcesPrivacy;
use App\Models\SocialMediaAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SocialMediaController extends Controller
{
    use EnforcesPrivacy;
    public function index(Request $request, int $userId): JsonResponse
    {
        $authUser = $request->user();
        if (! $authUser || ((int) $authUser->id !== $userId && ! $this->isAdmin($authUser))) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu sosyal medya hesaplarini gorme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        $accounts = SocialMediaAccount::where('user_id', $userId)
            ->orderBy('platform')
            ->get()
            ->map(fn (SocialMediaAccount $account) => $this->transformAccount($account))
            ->values();

        return response()->json(['ok' => true, 'data' => $accounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'in:twitter,instagram,facebook,youtube,tiktok,linkedin'],
            'username' => ['required', 'string', 'max:255'],
            'url'      => ['required', 'url'],
        ]);

        $account = SocialMediaAccount::updateOrCreate(
            ['user_id' => $request->user()->id, 'platform' => $validated['platform']],
            $validated + ['follower_count' => 0]
        );

        return response()->json(
            ['ok' => true, 'message' => 'Sosyal medya hesabı eklendi.', 'data' => $this->transformAccount($account)],
            Response::HTTP_CREATED
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $account = SocialMediaAccount::where('user_id', $request->user()->id)->find($id);

        if (! $account) {
            return response()->json(['ok' => false, 'message' => 'Hesap bulunamadı.'], Response::HTTP_NOT_FOUND);
        }

        $account->delete();

        return response()->json(['ok' => true, 'message' => 'Sosyal medya hesabı silindi.']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = SocialMediaAccount::where('user_id', $request->user()->id)->find($id);

        if (! $account) {
            return response()->json(['ok' => false, 'message' => 'Hesap bulunamadı.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'username'      => ['sometimes', 'string', 'max:255'],
            'url'          => ['sometimes', 'url'],
            'follower_count' => ['sometimes', 'integer', 'min:0'],
            'verified'     => ['sometimes', 'boolean'],
        ]);

        $account->update($validated);

        return response()->json(['ok' => true, 'message' => 'Güncellendi.', 'data' => $this->transformAccount($account->fresh())]);
    }

    private function transformAccount(SocialMediaAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'platform' => (string) $account->platform,
            'username' => (string) $account->username,
            'url' => (string) $account->url,
            'follower_count' => (int) ($account->follower_count ?? 0),
            'verified' => (bool) $account->verified,
            'created_at' => optional($account->created_at)?->toIso8601String(),
            'updated_at' => optional($account->updated_at)?->toIso8601String(),
        ];
    }
}
