<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LawyerWorkspaceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawyerWorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (string) $user->role !== 'lawyer') {
            return response()->json([
                'ok' => false,
                'message' => 'Bu alan sadece avukat hesaplari icin kullanilabilir.',
            ], 403);
        }

        $this->seedDefaults($user->id);

        $items = LawyerWorkspaceItem::query()
            ->where('user_id', $user->id)
            ->orderBy('item_type')
            ->orderByDesc('id')
            ->get()
            ->groupBy('item_type');

        return response()->json([
            'ok' => true,
            'data' => [
                'contracts' => ($items->get('contract') ?? collect())->values(),
                'consults' => ($items->get('consult') ?? collect())->values(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();
        if (! $user || (string) $user->role !== 'lawyer') {
            return response()->json([
                'ok' => false,
                'message' => 'Bu alan sadece avukat hesaplari icin kullanilabilir.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,review,done'],
        ]);

        $item = LawyerWorkspaceItem::query()
            ->where('user_id', $user->id)
            ->findOrFail($itemId);

        $item->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'ok' => true,
            'data' => $item->fresh(),
            'message' => 'Kayit durumu guncellendi.',
        ]);
    }

    private function seedDefaults(int $userId): void
    {
        $hasItems = LawyerWorkspaceItem::query()
            ->where('user_id', $userId)
            ->exists();

        if ($hasItems) {
            return;
        }

        $rows = [
            [
                'user_id' => $userId,
                'item_type' => 'contract',
                'title' => 'Transfer Sozlesmesi - Ahmet Y.',
                'counterparty' => 'Manager',
                'fee_label' => 'EUR 2.500',
                'priority' => null,
                'status' => 'pending',
                'deadline' => '2026-03-10',
            ],
            [
                'user_id' => $userId,
                'item_type' => 'contract',
                'title' => 'Kulup Anlasma Revizyonu',
                'counterparty' => 'Kulup',
                'fee_label' => 'EUR 4.200',
                'priority' => null,
                'status' => 'review',
                'deadline' => '2026-03-12',
            ],
            [
                'user_id' => $userId,
                'item_type' => 'contract',
                'title' => 'Oyuncu Temsil Sozlesmesi',
                'counterparty' => 'Scout',
                'fee_label' => 'EUR 1.800',
                'priority' => null,
                'status' => 'done',
                'deadline' => '2026-03-06',
            ],
            [
                'user_id' => $userId,
                'item_type' => 'consult',
                'title' => 'Fesih Maddesi Danismanligi',
                'counterparty' => 'Manager',
                'fee_label' => null,
                'priority' => 'Yuksek',
                'status' => 'pending',
                'deadline' => null,
            ],
            [
                'user_id' => $userId,
                'item_type' => 'consult',
                'title' => 'Bonservis Uyusmazligi',
                'counterparty' => 'Kulup',
                'fee_label' => null,
                'priority' => 'Orta',
                'status' => 'review',
                'deadline' => null,
            ],
            [
                'user_id' => $userId,
                'item_type' => 'consult',
                'title' => 'Menajerlik Yetki Kontrolu',
                'counterparty' => 'Player',
                'fee_label' => null,
                'priority' => 'Dusuk',
                'status' => 'done',
                'deadline' => null,
            ],
        ];

        LawyerWorkspaceItem::query()->insert($rows);
    }
}
