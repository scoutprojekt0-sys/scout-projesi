<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'min:1'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $toUser = DB::table('users')->where('id', (int) $validated['to_user_id'])->first();
        if (!$toUser) {
            return response()->json([
                'ok' => false,
                'message' => 'Alici kullanici bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ((int) $authUser->id === (int) $validated['to_user_id']) {
            return response()->json([
                'ok' => false,
                'message' => 'Kendinize mesaj gonderemezsiniz.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $id = DB::table('contacts')->insertGetId([
            'from_user_id' => (int) $authUser->id,
            'to_user_id' => (int) $validated['to_user_id'],
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created = DB::table('contacts')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Mesaj gonderildi.',
            'data' => $created,
        ], Response::HTTP_CREATED);
    }

    public function inbox(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'read', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('contacts')
            ->join('users as sender', 'sender.id', '=', 'contacts.from_user_id')
            ->where('contacts.to_user_id', (int) $authUser->id)
            ->select([
                'contacts.id',
                'contacts.subject',
                'contacts.message',
                'contacts.status',
                'contacts.created_at',
                'contacts.updated_at',
                'sender.id as sender_id',
                'sender.name as sender_name',
                'sender.role as sender_role',
            ]);

        if (!empty($validated['status'])) {
            $query->where('contacts.status', $validated['status']);
        }

        $inbox = $query
            ->orderByDesc('contacts.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
            ],
            'data' => $inbox,
        ]);
    }

    public function sent(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'read', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('contacts')
            ->join('users as recipient', 'recipient.id', '=', 'contacts.to_user_id')
            ->where('contacts.from_user_id', (int) $authUser->id)
            ->select([
                'contacts.id',
                'contacts.subject',
                'contacts.message',
                'contacts.status',
                'contacts.created_at',
                'contacts.updated_at',
                'recipient.id as recipient_id',
                'recipient.name as recipient_name',
                'recipient.role as recipient_role',
            ]);

        if (!empty($validated['status'])) {
            $query->where('contacts.status', $validated['status']);
        }

        $sent = $query
            ->orderByDesc('contacts.created_at')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'filters' => [
                'status' => $validated['status'] ?? null,
            ],
            'data' => $sent,
        ]);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        $validated = $request->validate([
            'status' => ['required', Rule::in(['read', 'archived'])],
        ]);

        $contact = DB::table('contacts')->where('id', $id)->first();
        if (!$contact) {
            return response()->json([
                'ok' => false,
                'message' => 'Mesaj bulunamadi.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ((int) $contact->to_user_id !== (int) $authUser->id) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu mesaji guncelleme yetkiniz yok.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::table('contacts')
            ->where('id', $id)
            ->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

        $updated = DB::table('contacts')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Mesaj durumu guncellendi.',
            'data' => $updated,
        ]);
    }
}
