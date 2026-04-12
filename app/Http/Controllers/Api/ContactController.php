<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\ChangeContactStatusRequest;
use App\Http\Requests\Contact\ListContactsRequest;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends Controller
{
    use ApiResponds;

    private const SEARCHABLE_ROLES = ['player', 'scout', 'team', 'club', 'manager', 'coach', 'lawyer'];

    public function sendMessage(StoreContactRequest $request): JsonResponse
    {
        return $this->store($request);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $toUserId = (int) $validated['to_user_id'];

        $this->authorize('create', [Contact::class, $toUserId]);

        $id = DB::table('contacts')->insertGetId([
            'from_user_id' => (int) $request->user()->id,
            'to_user_id'   => $toUserId,
            'subject'      => $validated['subject'] ?? null,
            'message'      => $validated['message'],
            'status'       => 'new',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $created = DB::table('contacts')->where('id', $id)->first();

        return $this->successResponse($this->transformContactRow($created), 'Mesaj gonderildi.', Response::HTTP_CREATED);
    }

    public function searchRecipients(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Yetkilendirme gerekli.', Response::HTTP_UNAUTHORIZED, 'unauthenticated');
        }

        $query = trim((string) $request->query('q', ''));
        $role = strtolower(trim((string) $request->query('role', '')));
        $limit = max(1, min((int) $request->query('limit', 10), 20));

        $rows = User::query()
            ->whereKeyNot($user->id)
            ->when($role !== '' && in_array($role, self::SEARCHABLE_ROLES, true), function ($builder) use ($role) {
                $builder->where('role', $role);
            }, function ($builder) {
                $builder->whereIn('role', self::SEARCHABLE_ROLES);
            })
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', '%'.$query.'%');
                });
            })
            ->orderByRaw('LOWER(name) = ? desc', [mb_strtolower($query, 'UTF-8')])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'role'])
            ->map(fn (User $recipient) => [
                'id' => (int) $recipient->id,
                'name' => (string) $recipient->name,
                'role' => (string) $recipient->role,
            ])
            ->values();

        return $this->successResponse($rows, 'Alici listesi hazir.');
    }

    public function inbox(ListContactsRequest $request): JsonResponse
    {
        $this->authorize('viewInbox', Contact::class);

        $validated = $request->validated();
        $perPage  = (int) ($validated['per_page'] ?? 20);
        $sortBy   = $validated['sort_by'] ?? 'created_at';
        $sortDir  = $validated['sort_dir'] ?? 'desc';

        $query = DB::table('contacts')
            ->join('users as sender', 'sender.id', '=', 'contacts.from_user_id')
            ->where('contacts.to_user_id', (int) $request->user()->id)
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
                DB::raw("(case when contacts.status = 'new' then 0 else 1 end) as is_read"),
            ]);

        if (! empty($validated['status'])) {
            $query->where('contacts.status', $validated['status']);
        }

        if ($request->boolean('unread_only')) {
            $query->where('contacts.status', 'new');
        }

        $sortColumnMap = [
            'created_at' => 'contacts.created_at',
            'status'     => 'contacts.status',
        ];

        $inbox = $query->orderBy($sortColumnMap[$sortBy], $sortDir)->paginate($perPage);

        $unreadCount = DB::table('contacts')
            ->where('to_user_id', (int) $request->user()->id)
            ->where('status', 'new')
            ->count();

        return $this->successResponse($inbox, 'Gelen kutusu hazir.', 200, [
            'unread_count' => $unreadCount,
            'filters'      => [
                'status'     => $validated['status'] ?? null,
                'unread_only' => $request->boolean('unread_only'),
                'sort_by'    => $sortBy,
                'sort_dir'   => $sortDir,
            ],
        ]);
    }

    public function sent(ListContactsRequest $request): JsonResponse
    {
        $this->authorize('viewSent', Contact::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $sortBy  = $validated['sort_by'] ?? 'created_at';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        $query = DB::table('contacts')
            ->join('users as recipient', 'recipient.id', '=', 'contacts.to_user_id')
            ->where('contacts.from_user_id', (int) $request->user()->id)
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

        if (! empty($validated['status'])) {
            $query->where('contacts.status', $validated['status']);
        }

        $sortColumnMap = [
            'created_at' => 'contacts.created_at',
            'status'     => 'contacts.status',
        ];

        $sent = $query->orderBy($sortColumnMap[$sortBy], $sortDir)->paginate($perPage);

        return $this->successResponse($sent, 'Gonderilen mesajlar hazir.', 200, [
            'filters' => [
                'status'   => $validated['status'] ?? null,
                'sort_by'  => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function changeStatus(ChangeContactStatusRequest $request, int $id): JsonResponse
    {
        $contact = Contact::query()->find($id);
        if (! $contact) {
            return $this->errorResponse('Mesaj bulunamadi.', Response::HTTP_NOT_FOUND, 'contact_not_found');
        }

        $this->authorize('changeStatus', $contact);

        DB::table('contacts')
            ->where('id', $id)
            ->update(['status' => $request->validated('status'), 'updated_at' => now()]);

        return $this->successResponse(
            $this->transformContactRow(DB::table('contacts')->where('id', $id)->first()),
            'Mesaj durumu guncellendi.'
        );
    }

    public function readMessage(int $id, Request $request): JsonResponse
    {
        $contact = Contact::query()->find($id);
        if (! $contact) {
            return $this->errorResponse('Mesaj bulunamadi.', Response::HTTP_NOT_FOUND, 'contact_not_found');
        }

        if ((int) $contact->to_user_id !== (int) $request->user()->id) {
            return $this->errorResponse('Bu mesaji okuma yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        DB::table('contacts')
            ->where('id', $id)
            ->update(['status' => 'read', 'updated_at' => now()]);

        return $this->successResponse(
            $this->transformContactRow(DB::table('contacts')->where('id', $id)->first()),
            'Mesaj okundu olarak isaretlendi.'
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        DB::table('contacts')
            ->where('to_user_id', (int) $request->user()->id)
            ->where('status', 'new')
            ->update(['status' => 'read', 'updated_at' => now()]);

        return $this->successResponse(null, 'Tum mesajlar okundu olarak isaretlendi.');
    }

    public function archiveMessage(int $id, Request $request): JsonResponse
    {
        $contact = Contact::query()->find($id);
        if (! $contact) {
            return $this->errorResponse('Mesaj bulunamadi.', Response::HTTP_NOT_FOUND, 'contact_not_found');
        }

        if (
            (int) $contact->to_user_id !== (int) $request->user()->id
            && (int) $contact->from_user_id !== (int) $request->user()->id
        ) {
            return $this->errorResponse('Bu islemi yapma yetkiniz yok.', Response::HTTP_FORBIDDEN, 'forbidden');
        }

        DB::table('contacts')
            ->where('id', $id)
            ->update(['status' => 'archived', 'updated_at' => now()]);

        return $this->successResponse(null, 'Mesaj arsivlendi.');
    }

    private function transformContactRow(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'from_user_id' => (int) $row->from_user_id,
            'to_user_id' => (int) $row->to_user_id,
            'subject' => $row->subject,
            'message' => (string) $row->message,
            'status' => (string) $row->status,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }
}
