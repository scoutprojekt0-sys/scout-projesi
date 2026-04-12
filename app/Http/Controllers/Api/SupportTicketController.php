<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with('assignedTo:id,name,role,city')
            ->withCount('messages')
            ->latest('id')
            ->paginate(20)
            ->through(fn (SupportTicket $ticket) => $this->transformTicket($ticket, false));

        return response()->json([
            'ok' => true,
            'data' => $tickets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'category' => ['required', 'in:technical,account,billing,general'],
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => (int) $request->user()->id,
            'title' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? 'medium',
            'category' => $validated['category'],
            'status' => 'open',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Destek talebi olusturuldu.',
            'data' => $this->transformTicket($ticket, false),
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->with(['messages.user:id,name,role,city', 'assignedTo:id,name,role,city'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'data' => $this->transformTicket($ticket, true),
        ]);
    }

    public function addMessage(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = SupportTicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => (int) $request->user()->id,
            'message' => $validated['message'],
            'is_staff_reply' => false,
        ]);

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Mesaj eklendi.',
            'data' => $this->transformMessage($message),
        ], Response::HTTP_CREATED);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Destek talebi kapatildi.',
        ]);
    }

    private function transformTicket(SupportTicket $ticket, bool $includeMessages): array
    {
        $payload = [
            'id' => (int) $ticket->id,
            'title' => (string) $ticket->title,
            'description' => (string) $ticket->description,
            'category' => (string) $ticket->category,
            'priority' => (string) $ticket->priority,
            'status' => (string) $ticket->status,
            'messages_count' => isset($ticket->messages_count) ? (int) $ticket->messages_count : null,
            'resolved_at' => optional($ticket->resolved_at)?->toIso8601String(),
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'updated_at' => optional($ticket->updated_at)?->toIso8601String(),
            'assigned_to' => $ticket->relationLoaded('assignedTo') && $ticket->assignedTo ? [
                'id' => (int) $ticket->assignedTo->id,
                'name' => (string) $ticket->assignedTo->name,
                'role' => (string) $ticket->assignedTo->role,
                'city' => (string) ($ticket->assignedTo->city ?? ''),
            ] : null,
        ];

        if ($includeMessages) {
            $payload['messages'] = $ticket->messages
                ->map(fn (SupportTicketMessage $message) => $this->transformMessage($message, true))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function transformMessage(SupportTicketMessage $message, bool $includeUser = false): array
    {
        return [
            'id' => (int) $message->id,
            'ticket_id' => (int) $message->ticket_id,
            'message' => (string) $message->message,
            'is_staff_reply' => (bool) $message->is_staff_reply,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'updated_at' => optional($message->updated_at)?->toIso8601String(),
            'user' => $includeUser && $message->relationLoaded('user') && $message->user ? [
                'id' => (int) $message->user->id,
                'name' => (string) $message->user->name,
                'role' => (string) $message->user->role,
                'city' => (string) ($message->user->city ?? ''),
            ] : null,
        ];
    }
}
