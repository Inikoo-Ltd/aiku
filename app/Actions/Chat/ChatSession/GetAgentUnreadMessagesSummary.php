<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentUnreadMessagesSummary
{
    use AsAction;
    use WithChatAgentAuthorisation;

    private const int LIVE_MINUTES = 30;

    private const array NOBODY_WAITING = [
        'sessions'  => 0,
        'oldest_at' => null,
        'url'       => null,
        'live'      => ['sessions' => 0, 'oldest_at' => null, 'url' => null],
    ];

    public function handle(ChatAgent $agent): array
    {
        $shopIds = collect($this->shopIdsWorkedBy($agent->user_id));

        if ($shopIds->isEmpty()) {
            return $this->emptySummary();
        }

        $visitorSenderTypes = [
            ChatSenderTypeEnum::GUEST->value,
            ChatSenderTypeEnum::USER->value,
        ];

        $assigned = $this->tally(
            ChatMessage::query()
                ->unread()
                ->whereIn('sender_type', $visitorSenderTypes)
                ->whereHas('chatSession', function ($query) use ($agent, $shopIds) {
                    $query->whereIn('shop_id', $shopIds)
                        ->where('is_spam', false)
                        ->whereHas('assignments', function ($assignmentQuery) use ($agent) {
                            $assignmentQuery->where('chat_agent_id', $agent->id)
                                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
                        });
                })
        );

        $unassigned = $this->tally(
            ChatMessage::query()
                ->unread()
                ->whereIn('sender_type', $visitorSenderTypes)
                ->whereHas('chatSession', function ($query) use ($shopIds) {
                    $query->where('status', ChatSessionStatusEnum::WAITING->value)
                        ->where('is_spam', false)
                        ->whereIn('shop_id', $shopIds)
                        ->whereDoesntHave('assignments', function ($assignmentQuery) {
                            $assignmentQuery->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
                        });
                })
        );

        // The badge is one number over every channel the agent works in: a WhatsApp
        // customer waiting is no less urgent than a website one, and splitting the count
        // would mean the rail could read zero while someone is still unanswered.
        $whatsapp = $this->whatsappCounts($agent, $shopIds);

        $messages = fn (array $tallies): int => collect($tallies)->flatten(1)->sum('messages');

        return [
            'assigned_unread_count'   => $messages([$assigned, $whatsapp['assigned']]),
            'unassigned_unread_count' => $messages([$unassigned, $whatsapp['unassigned']]),
            'total_unread_count'      => $messages([$assigned, $unassigned, $whatsapp['assigned'], $whatsapp['unassigned']]),
            'by_channel'              => [
                'website'  => [
                    'assigned'   => $messages([$assigned]),
                    'unassigned' => $messages([$unassigned]),
                ],
                'whatsapp' => [
                    'assigned'   => $messages([$whatsapp['assigned']]),
                    'unassigned' => $messages([$whatsapp['unassigned']]),
                ],
            ],
            'waiting'                 => $this->waiting(collect([$assigned, $unassigned, $whatsapp['assigned'], $whatsapp['unassigned']])->flatten(1)),
        ];
    }

    /**
     * A meta session is never stored as `waiting`: it is written as `active` and stays there,
     * so an unanswered WhatsApp chat is one that is not closed and has nobody assigned. Only
     * guest messages count, or a marketing broadcast would put thousands on the rail.
     *
     * @return array{assigned: list<array<string, mixed>>, unassigned: list<array<string, mixed>>}
     */
    protected function whatsappCounts(ChatAgent $agent, $shopIds): array
    {
        $assigned = MetaChatMessage::query()
            ->where('is_read', false)
            ->where('sender_type', ChatSenderTypeEnum::GUEST->value)
            ->whereHas('metaChatSession', function ($query) use ($agent, $shopIds) {
                $query->whereIn('shop_id', $shopIds)
                    ->where('is_spam', false)
                    ->whereHas('assignments', function ($assignmentQuery) use ($agent) {
                        $assignmentQuery->where('chat_agent_id', $agent->id)
                            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
                    });
            });

        $unassigned = MetaChatMessage::query()
            ->where('is_read', false)
            ->where('sender_type', ChatSenderTypeEnum::GUEST->value)
            ->whereHas('metaChatSession', function ($query) use ($shopIds) {
                $query->where('status', '!=', ChatSessionStatusEnum::CLOSED->value)
                    ->where('is_spam', false)
                    ->whereIn('shop_id', $shopIds)
                    ->whereDoesntHave('assignments', function ($assignmentQuery) {
                        $assignmentQuery->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
                    });
            });

        return ['assigned' => $this->tally($assigned), 'unassigned' => $this->tally($unassigned)];
    }

    /**
     * One row per channel of the unread customer messages the query finds: how many, in how
     * many conversations, since when, and which conversation has waited longest. The wait is
     * read from the first unread message, which is when the customer was last left unanswered.
     * The live figures only look at messages from the last few minutes: a customer who wrote
     * then is still at the screen, one who wrote days ago is backlog.
     *
     * @return list<array{channel: string, messages: int, all: array{sessions: int, oldest_at: ?string, oldest_session_id: ?int}, live: array{sessions: int, oldest_at: ?string, oldest_session_id: ?int}, session_model: class-string}>
     */
    private function tally(Builder $query): array
    {
        $isWhatsapp    = $query->getModel() instanceof MetaChatMessage;
        $sessionColumn = $query->getModel()->qualifyColumn($isWhatsapp ? 'meta_chat_session_id' : 'chat_session_id');
        $createdAt     = $query->getModel()->qualifyColumn('created_at');
        $channel       = $isWhatsapp
            ? "'whatsapp'"
            : "(select chat_sessions.channel from chat_sessions where chat_sessions.id = $sessionColumn)";
        $liveSince     = now()->subMinutes(self::LIVE_MINUTES);

        return $query->toBase()
            ->selectRaw(
                "$channel as channel, count(*) as messages,
                count(distinct $sessionColumn) as sessions, min($createdAt) as oldest_at, (array_agg($sessionColumn order by $createdAt))[1] as oldest_session_id,
                count(distinct $sessionColumn) filter (where $createdAt > ?) as live_sessions, min($createdAt) filter (where $createdAt > ?) as live_oldest_at, (array_agg($sessionColumn order by $createdAt) filter (where $createdAt > ?))[1] as live_oldest_session_id",
                [$liveSince, $liveSince, $liveSince]
            )
            ->groupByRaw('1')
            ->get()
            ->map(fn ($row) => [
                'channel'       => (string) $row->channel,
                'messages'      => (int) $row->messages,
                'all'           => [
                    'sessions'          => (int) $row->sessions,
                    'oldest_at'         => $row->oldest_at,
                    'oldest_session_id' => $row->oldest_session_id ? (int) $row->oldest_session_id : null,
                ],
                'live'          => [
                    'sessions'          => (int) $row->live_sessions,
                    'oldest_at'         => $row->live_oldest_at,
                    'oldest_session_id' => $row->live_oldest_session_id ? (int) $row->live_oldest_session_id : null,
                ],
                'session_model' => $isWhatsapp ? MetaChatSession::class : ChatSession::class,
            ])
            ->all();
    }

    /**
     * Customers waiting, split the way they have to be answered: a website or WhatsApp chat
     * has somebody watching the screen for a reply, an email can wait its turn.
     *
     * @return array{chat: array<string, mixed>, email: array<string, mixed>}
     */
    private function waiting(Collection $tallies): array
    {
        return [
            'chat'  => $this->waitingIn($tallies->where('channel', '!=', 'email')),
            'email' => $this->waitingIn($tallies->where('channel', 'email')),
        ];
    }

    /**
     * @return array{sessions: int, oldest_at: ?string, url: ?string, live: array{sessions: int, oldest_at: ?string, url: ?string}}
     */
    private function waitingIn(Collection $tallies): array
    {
        return [
            ...$this->oldestIn($tallies, 'all'),
            'live' => $this->oldestIn($tallies, 'live'),
        ];
    }

    /**
     * @return array{sessions: int, oldest_at: ?string, url: ?string}
     */
    private function oldestIn(Collection $tallies, string $span): array
    {
        $oldest = $tallies->filter(fn (array $tally) => $tally[$span]['oldest_at'])->sortBy(fn (array $tally) => $tally[$span]['oldest_at'])->first();

        return [
            'sessions'  => (int) $tallies->sum(fn (array $tally) => $tally[$span]['sessions']),
            'oldest_at' => $oldest ? Carbon::parse($oldest[$span]['oldest_at'])->toIso8601String() : null,
            'url'       => $oldest ? $this->conversationUrl($oldest['session_model'], $oldest[$span]['oldest_session_id']) : null,
        ];
    }

    private function conversationUrl(string $sessionModel, ?int $sessionId): ?string
    {
        $session          = $sessionId ? $sessionModel::with('shop.organisation')->find($sessionId) : null;
        $organisationSlug = $session?->shop?->organisation?->slug;

        if (!$organisationSlug) {
            return null;
        }

        return $session instanceof ChatSession
            ? route('grp.org.chat.inbox.conversation', [$organisationSlug, $session->ulid])
            : route('grp.org.chat.inbox', [$organisationSlug]);
    }

    private function emptySummary(): array
    {
        return [
            'assigned_unread_count'   => 0,
            'unassigned_unread_count' => 0,
            'total_unread_count'      => 0,
            'waiting'                 => [
                'chat'  => self::NOBODY_WAITING,
                'email' => self::NOBODY_WAITING,
            ],
        ];
    }

    /**
     * The id in the URL is the caller's own: user ids are sequential, and an agent's queue names
     * the customers they are talking to, so another agent's is not theirs to read.
     */
    public function asController(ActionRequest $request, $userId): JsonResponse
    {
        abort_unless((int) $userId === $request->user()?->id, 403);

        $user = User::find($userId);

        if (!$user || !$user->chatAgent) {
            return response()->json([
                'success' => true,
                'message' => 'User is not a chat agent',
                'data'    => $this->emptySummary(),
            ]);
        }

        $summary = $this->handle($user->chatAgent);

        return response()->json([
            'success' => true,
            'message' => 'Unread message summary retrieved successfully',
            'data'    => $summary,
        ]);
    }
}
