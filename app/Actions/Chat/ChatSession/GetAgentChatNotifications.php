<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentChatNotifications
{
    use AsAction;

    private const GROUP_LIMIT = 20;

    private array $visitorSenderTypes = [
        ChatSenderTypeEnum::GUEST->value,
        ChatSenderTypeEnum::USER->value,
    ];

    public function handle(ChatAgent $agent): array
    {
        $shopIds = $agent->shops()->pluck('shops.id');

        if ($shopIds->isEmpty()) {
            return [
                'waiting' => [],
                'active'  => [],
                'reopen'  => [],
            ];
        }

        return [
            'waiting' => $this->waitingSessions($shopIds),
            'active'  => $this->activeAssignedSessions($agent, $shopIds),
            'reopen'  => $this->reopenableSessions($shopIds),
        ];
    }

    private function baseQuery()
    {
        return ChatSession::with([
            'messages' => fn ($q) => $q->latest()->limit(1),
            'chatEvents' => fn ($q) => $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1),
            'webUser',
            'shop',
            'assignments.chatAgent.user',
        ])
            ->whereHas('messages')
            ->withLastMessageTime()
            ->orderBy('last_message_at', 'desc');
    }

    private function withUnreadVisitorMessages(Builder $query): Builder
    {
        return $query->whereHas('messages', function ($messageQuery) {
            $messageQuery->where('is_read', false)
                ->whereIn('sender_type', $this->visitorSenderTypes);
        });
    }

    private function waitingSessions($shopIds): array
    {
        $sessions = $this->baseQuery()
            ->where('status', ChatSessionStatusEnum::WAITING->value)
            ->whereIn('shop_id', $shopIds)
            ->whereDoesntHave('assignments', function ($assignmentQuery) {
                $assignmentQuery->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            })
            ->limit(self::GROUP_LIMIT)
            ->get();

        return ChatSessionListResource::collection($sessions)->resolve();
    }

    private function activeAssignedSessions(ChatAgent $agent, $shopIds): array
    {
        $query = $this->baseQuery()
            ->where('status', ChatSessionStatusEnum::ACTIVE->value)
            ->whereIn('shop_id', $shopIds)
            ->whereHas('assignments', function ($assignmentQuery) use ($agent) {
                $assignmentQuery->where('chat_agent_id', $agent->id)
                    ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            });

        $sessions = $this->withUnreadVisitorMessages($query)
            ->limit(self::GROUP_LIMIT)
            ->get();

        return ChatSessionListResource::collection($sessions)->resolve();
    }

    private function reopenableSessions($shopIds): array
    {
        $query = $this->baseQuery()
            ->where('status', ChatSessionStatusEnum::CLOSED->value)
            ->whereIn('shop_id', $shopIds);

        $sessions = $this->withUnreadVisitorMessages($query)
            ->limit(self::GROUP_LIMIT)
            ->get();

        return ChatSessionListResource::collection($sessions)->resolve();
    }

    public function asController(ActionRequest $request, $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user || !$user->chatAgent) {
            return response()->json([
                'success' => true,
                'message' => 'User is not a chat agent',
                'data'    => [
                    'waiting' => [],
                    'active'  => [],
                    'reopen'  => [],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Agent chat notifications retrieved successfully',
            'data'    => $this->handle($user->chatAgent),
        ]);
    }
}
