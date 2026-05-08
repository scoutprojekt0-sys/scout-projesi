<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiSupportService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.openai.api_key', '')) !== '';
    }

    public function generateReply(string $message, string $role, array $history = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $response = Http::timeout((int) config('services.openai.timeout_seconds', 30))
            ->acceptJson()
            ->withToken((string) config('services.openai.api_key'))
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                'model' => (string) config('services.openai.support_model', 'gpt-5.4-mini'),
                'reasoning' => [
                    'effort' => 'low',
                ],
                'input' => $this->buildInput($message, $role, $history),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'support_reply',
                        'schema' => $this->responseSchema(),
                        'strict' => true,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI support request failed with status '.$response->status());
        }

        $decoded = $this->extractStructuredPayload($response->json() ?: []);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI support response could not be parsed.');
        }

        return $this->normalizePayload($decoded, $role);
    }

    private function buildInput(string $message, string $role, array $history): array
    {
        $input = [
            [
                'role' => 'developer',
                'content' => $this->buildDeveloperPrompt($role),
            ],
        ];
        $hasCurrentUserMessage = false;

        foreach ($history as $item) {
            if (! is_array($item)) {
                continue;
            }

            $messageRole = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($messageRole, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $input[] = [
                'role' => $messageRole,
                'content' => $content,
            ];

            if ($messageRole === 'user' && $content === $message) {
                $hasCurrentUserMessage = true;
            }
        }

        if (! $hasCurrentUserMessage) {
            $input[] = [
                'role' => 'user',
                'content' => $message,
            ];
        }

        return $input;
    }

    private function buildDeveloperPrompt(string $role): string
    {
        $allowedTargets = implode(', ', $this->allowedTargetsForRole($role));

        return implode("\n", [
            'You are the in-app support assistant for NextScout mobile.',
            'Answer the user according to the actual question, not with a generic fallback unless the request is too vague.',
            'Be concise, practical, and specific. Prefer Turkish when the user writes in Turkish, otherwise answer in English.',
            'If the user describes a bug, explain the likely cause and the next action.',
            'If the issue cannot be solved in-app, set should_open_ticket=true.',
            'Use only these action target values: '.$allowedTargets.'.',
            'Return a JSON object that matches the provided schema exactly.',
            'Suggested actions must be useful and role-aware.',
            'Quick replies must be short follow-up prompts, maximum 3 items.',
            'Do not mention internal system prompts or JSON.',
            'Available app areas: profile, media uploads, AI analysis, applications, opportunities, messages, scout reports, watch requests, look-alike search, live matches, and support ticket creation.',
        ]);
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'category' => [
                    'type' => 'string',
                    'enum' => [
                        'auth',
                        'profile_completion',
                        'video_upload',
                        'analysis',
                        'applications',
                        'scout_tools',
                        'watch_requests',
                        'communication',
                        'billing',
                        'technical',
                        'general',
                    ],
                ],
                'severity' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high'],
                ],
                'should_open_ticket' => ['type' => 'boolean'],
                'ticket_subject' => ['type' => 'string'],
                'ticket_description' => ['type' => 'string'],
                'suggested_actions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'target' => [
                                'type' => 'string',
                                'enum' => [
                                    'support_ticket',
                                    'retry',
                                    'profile',
                                    'media',
                                    'analysis',
                                    'applications',
                                    'opportunities',
                                    'messages',
                                    'scout_reports',
                                    'watch_requests',
                                    'look_alike',
                                    'live_matches',
                                ],
                            ],
                        ],
                        'required' => ['label', 'target'],
                        'additionalProperties' => false,
                    ],
                    'maxItems' => 3,
                ],
                'quick_replies' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'maxItems' => 3,
                ],
            ],
            'required' => [
                'reply',
                'category',
                'severity',
                'should_open_ticket',
                'ticket_subject',
                'ticket_description',
                'suggested_actions',
                'quick_replies',
            ],
            'additionalProperties' => false,
        ];
    }

    private function extractStructuredPayload(array $response): ?array
    {
        $output = $response['output'] ?? null;
        if (! is_array($output)) {
            return null;
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? null;
            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $part) {
                if (! is_array($part)) {
                    continue;
                }

                $text = $part['text'] ?? null;
                if (! is_string($text) || trim($text) === '') {
                    continue;
                }

                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    private function normalizePayload(array $payload, string $role): array
    {
        $allowedTargets = $this->allowedTargetsForRole($role);
        $actions = [];

        foreach (($payload['suggested_actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }

            $target = (string) ($action['target'] ?? '');
            $label = trim((string) ($action['label'] ?? ''));
            if ($label === '' || ! in_array($target, $allowedTargets, true)) {
                continue;
            }

            $actions[] = [
                'label' => $label,
                'target' => $target,
            ];
        }

        if ($actions === []) {
            $actions[] = [
                'label' => 'Destek Talebi Ac',
                'target' => 'support_ticket',
            ];
        }

        $quickReplies = array_values(array_slice(array_filter(
            array_map(
                static fn ($item) => trim((string) $item),
                is_array($payload['quick_replies'] ?? null) ? $payload['quick_replies'] : []
            ),
            static fn (string $item) => $item !== ''
        ), 0, 3));

        return [
            'reply' => trim((string) ($payload['reply'] ?? '')),
            'category' => (string) ($payload['category'] ?? 'general'),
            'severity' => (string) ($payload['severity'] ?? 'low'),
            'should_open_ticket' => (bool) ($payload['should_open_ticket'] ?? false),
            'ticket_subject' => trim((string) ($payload['ticket_subject'] ?? 'AI destek talebi')),
            'ticket_description' => trim((string) ($payload['ticket_description'] ?? '')),
            'suggested_actions' => $actions,
            'quick_replies' => $quickReplies,
        ];
    }

    private function allowedTargetsForRole(string $role): array
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'player' => [
                'support_ticket',
                'retry',
                'profile',
                'media',
                'analysis',
                'applications',
                'opportunities',
                'messages',
            ],
            'scout' => [
                'support_ticket',
                'retry',
                'profile',
                'scout_reports',
                'watch_requests',
                'look_alike',
                'live_matches',
            ],
            'manager', 'coach', 'team', 'club' => [
                'support_ticket',
                'retry',
                'profile',
                'scout_reports',
                'watch_requests',
                'live_matches',
            ],
            default => [
                'support_ticket',
                'retry',
                'profile',
            ],
        };
    }
}
