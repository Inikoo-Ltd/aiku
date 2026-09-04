<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The WhatsApp half of the agent's badges. Without it the bell only counts website chats,
 * and a WhatsApp customer can sit unanswered — which costs more there, because once the
 * 24-hour window closes only an approved template can reach them again.
 */
class GetAgentMetaChatNotifications
{
    use AsAction;

    private const GROUP_LIMIT = 20;

    /**
     * @return array{waiting: array, active: array, reopen: array}
     */
    public function handle(ChatAgent $agent): array
    {
        $shopIds = $agent->shops()->pluck('shops.id');

        if ($shopIds->isEmpty()) {
            return ['waiting' => [], 'active' => [], 'reopen' => []];
        }

        return [
            'waiting' => $this->waitingSessions($shopIds),
            'active'  => $this->activeAssignedSessions($agent, $shopIds),
            'reopen'  => $this->reopenableSessions($shopIds),
        ];
    }

    private function baseQuery(Collection $shopIds): Builder
    {
        return MetaChatSession::with(['shop.organisation', 'customer', 'assignments.chatAgent.user'])
            ->whereHas('messages')
            ->where('is_spam', false)
            ->whereIn('shop_id', $shopIds)
            ->orderByDesc('last_visitor_message_at');
    }

    private function withUnreadVisitorMessages(Builder $query): Builder
    {
        return $query->whereHas('messages', function ($messageQuery) {
            $messageQuery->where('is_read', false)
                ->where('sender_type', ChatSenderTypeEnum::GUEST->value);
        });
    }

    private function waitingSessions(Collection $shopIds): array
    {
        $sessions = $this->baseQuery($shopIds)
            ->where('status', ChatSessionStatusEnum::WAITING->value)
            ->whereDoesntHave('assignments', function ($assignmentQuery) {
                $assignmentQuery->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            })
            ->limit(self::GROUP_LIMIT)
            ->get();

        return MetaChatSessionListResource::collection($sessions)->resolve();
    }

    private function activeAssignedSessions(ChatAgent $agent, Collection $shopIds): array
    {
        $query = $this->baseQuery($shopIds)
            ->where('status', ChatSessionStatusEnum::ACTIVE->value)
            ->whereHas('assignments', function ($assignmentQuery) use ($agent) {
                $assignmentQuery->where('chat_agent_id', $agent->id)
                    ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            });

        return MetaChatSessionListResource::collection(
            $this->withUnreadVisitorMessages($query)->limit(self::GROUP_LIMIT)->get()
        )->resolve();
    }

    private function reopenableSessions(Collection $shopIds): array
    {
        $query = $this->baseQuery($shopIds)
            ->where('status', ChatSessionStatusEnum::CLOSED->value);

        return MetaChatSessionListResource::collection(
            $this->withUnreadVisitorMessages($query)->limit(self::GROUP_LIMIT)->get()
        )->resolve();
    }
}
