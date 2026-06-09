<?php

namespace App\Actions\CRM\ChatSession;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportChatConversations
{
    use AsAction;

    public function handle(Organisation $organisation, array $filters): StreamedResponse
    {
        $format       = Arr::get($filters, 'format', 'jsonl');
        $systemPrompt = Arr::get($filters, 'system_prompt', 'You are a helpful customer service agent. Be professional, concise, and helpful.');
        $minTurns     = (int) Arr::get($filters, 'min_turns', 2);
        $shopId       = Arr::get($filters, 'shop_id');

        $query = ChatSession::query()
            ->whereHas('shop', function ($q) use ($organisation, $shopId) {
                $q->where('organisation_id', $organisation->id);
                if ($shopId) {
                    $q->where('id', $shopId);
                }
            })
            ->with([
                'webUser.customer',
                'shop',
                'assignments.chatAgent.user',
                'messages' => fn ($q) => $q->orderBy('created_at')
                    ->whereNotIn('sender_type', [
                        ChatSenderTypeEnum::SYSTEM->value,
                        ChatSenderTypeEnum::AI->value,
                    ]),
            ])
            ->where('status', ChatSessionStatusEnum::CLOSED)
            ->whereHas('messages');

        if ($status = Arr::get($filters, 'status')) {
            $query->where('status', $status);
        }

        if ($from = Arr::get($filters, 'from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = Arr::get($filters, 'to')) {
            $query->where('created_at', '<=', $to);
        }

        if ($sentiment = Arr::get($filters, 'sentiment')) {
            $query->whereRaw("metadata->'ai_summary'->>'sentiment' = ?", [$sentiment]);
        }

        $filename = now()->format('Y-m-d') . '-chat-training.' . $format;

        return response()->streamDownload(function () use ($query, $format, $systemPrompt, $minTurns) {
            if ($format === 'csv') {
                echo $this->csvHeader();
            }

            $query->chunk(100, function ($sessions) use ($format, $systemPrompt, $minTurns) {
                foreach ($sessions as $session) {
                    $turns = $this->buildTurns($session);

                    if (count($turns) < $minTurns) {
                        continue;
                    }

                    if ($format === 'jsonl') {
                        echo $this->toJsonl($session, $turns, $systemPrompt) . "\n";
                    } else {
                        foreach ($turns as $i => $turn) {
                            echo $this->toCsvRow($session, $turn, $i) . "\n";
                        }
                    }
                }
            });
        }, $filename, [
            'Content-Type' => $format === 'jsonl'
                ? 'application/x-ndjson'
                : 'text/csv',
        ]);
    }

    private function buildTurns(ChatSession $session): array
    {
        $turns = [];

        foreach ($session->messages as $msg) {
            if (!$msg->message_text) {
                continue;
            }

            $role = match ($msg->sender_type) {
                ChatSenderTypeEnum::AGENT => 'assistant',
                default                   => 'user',
            };

            $turns[] = [
                'role'    => $role,
                'content' => trim($msg->message_text),
            ];
        }

        return $turns;
    }

    private function toJsonl(ChatSession $session, array $turns, string $systemPrompt): string
    {
        $contactName = $session->webUser?->customer?->contact_name
            ?? $session->webUser?->username
            ?? $session->guest_identifier
            ?? 'Guest';

        $aiSummary = Arr::get($session->metadata ?? [], 'ai_summary');

        return json_encode([
            'id'       => $session->ulid,
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $turns
            ),
            'metadata' => [
                'shop'      => $session->shop?->name,
                'contact'   => $contactName,
                'status'    => $session->status->value,
                'started'   => $session->created_at?->toISOString(),
                'closed'    => $session->closed_at?->toISOString(),
                'sentiment' => Arr::get($aiSummary ?? [], 'sentiment'),
                'summary'   => Arr::get($aiSummary ?? [], 'summary'),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function csvHeader(): string
    {
        return implode(',', [
            '"session_id"',
            '"shop"',
            '"contact"',
            '"turn_index"',
            '"role"',
            '"content"',
            '"sentiment"',
            '"started_at"',
        ]) . "\n";
    }

    private function toCsvRow(ChatSession $session, array $turn, int $index): string
    {
        $contactName = $session->webUser?->customer?->contact_name
            ?? $session->webUser?->username
            ?? $session->guest_identifier
            ?? 'Guest';

        $sentiment = Arr::get($session->metadata ?? [], 'ai_summary.sentiment', '');

        return implode(',', array_map(
            fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
            [
                $session->ulid,
                $session->shop?->name ?? '',
                $contactName,
                $index,
                $turn['role'],
                $turn['content'],
                $sentiment,
                $session->created_at?->toISOString() ?? '',
            ]
        ));
    }

    public function asController(Organisation $organisation, Request $request): StreamedResponse
    {
        $filters = $request->all();

        if ($shopId = Arr::get($filters, 'shop_id')) {
            $belongs = $organisation->shops()->whereKey($shopId)->exists();
            if (! $belongs) {
                abort(404);
            }
        }

        return $this->handle($organisation, $filters);
    }
}
