<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\PublicContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicContactMessageController extends Controller
{
    use ApiResponds;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email:rfc,dns', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:1500'],
        ]);

        $message = PublicContactMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'message' => $validated['message'],
            'source' => 'homepage',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return $this->successResponse($this->transformPublicMessage($message, false), 'Mesaj alindi.', Response::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = PublicContactMessage::query()
            ->latest('id')
            ->paginate((int) $request->input('per_page', 100))
            ->through(fn (PublicContactMessage $message) => $this->transformPublicMessage($message, true));

        return $this->successResponse($rows, 'Iletisim mesajlari hazir.');
    }

    private function transformPublicMessage(PublicContactMessage $message, bool $includeEmail): array
    {
        $payload = [
            'id' => (int) $message->id,
            'name' => (string) $message->name,
            'message' => (string) $message->message,
            'source' => (string) $message->source,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'updated_at' => optional($message->updated_at)?->toIso8601String(),
        ];

        if ($includeEmail) {
            $payload['email'] = (string) $message->email;
        }

        return $payload;
    }
}
