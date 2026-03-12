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
            ->with('assignedTo:id,name,email,role,city')
            ->withCount('messages')
            ->latest('id')
            ->paginate(20);

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
            'data' => $ticket,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::query()
            ->with(['messages.user:id,name,email,role,city', 'assignedTo:id,name,email,role,city'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'ok' => true,
            'data' => $ticket,
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
            'data' => $message,
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
}
