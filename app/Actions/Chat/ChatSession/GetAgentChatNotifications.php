<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithUnclaimedChatSessions;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentChatNotifications
{
    use AsAction;
    use WithChatAgentAuthorisation;
    use WithUnclaimedChatSessions;

    private array $visitorSenderTypes = [
        ChatSenderTypeEnum::GUEST->value,
        ChatSenderTypeEnum::USER->value,
    ];

    public function handle(ChatAgent $agent): array
    {
        $shopIds = collect($this->shopIdsWorkedBy($agent->user_id));

        // Counted across every shop, not this agent's: the point of the unclaimed queue is that
        // somebody who does not work the shop is the one who ends up noticing.
        $unclaimed = $this->unclaimedChatSessions()->count()
            + $this->unclaimedMetaChatSessions()->count();

        if ($shopIds->isEmpty()) {
            return ['team_unread' => [], 'unclaimed' => $unclaimed];
        }

        return [
            'team_unread' => $this->teamUnreadByShop($agent, $shopIds),
            'unclaimed'   => $unclaimed,
        ];
    }

    /**
     * Informational per-shop count of team chats (active or closed) that carry unread
     * customer messages and are handled by a teammate — so others can spot and take them
     * over. Keyed by shop_id so the UI can show the count for the selected inbox only.
     *
     * @return array<int, int>
     */
    private function teamUnreadByShop(ChatAgent $agent, $shopIds): array
    {
        $teamAgentIds = $this->agentIdsCovering(collect($shopIds)->all(), $agent->id);

        if ($teamAgentIds === []) {
            return [];
        }

        return ChatSession::query()
            ->where('is_spam', false)
            ->whereIn('shop_id', $shopIds)
            ->whereIn('status', [
                ChatSessionStatusEnum::ACTIVE->value,
                ChatSessionStatusEnum::CLOSED->value,
            ])
            ->whereHas('assignments', function ($assignmentQuery) use ($teamAgentIds) {
                $assignmentQuery->whereIn('chat_agent_id', $teamAgentIds)
                    ->whereIn('status', [
                        ChatAssignmentStatusEnum::ACTIVE->value,
                        ChatAssignmentStatusEnum::RESOLVED->value,
                    ]);
            })
            ->whereDoesntHave('assignments', function ($assignmentQuery) use ($agent) {
                $assignmentQuery->where('chat_agent_id', $agent->id)
                    ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            })
            ->whereHas('messages', function ($messageQuery) {
                $messageQuery->where('is_read', false)
                    ->whereIn('sender_type', $this->visitorSenderTypes);
            })
            ->selectRaw('shop_id, count(*) as aggregate')
            ->groupBy('shop_id')
            ->pluck('aggregate', 'shop_id')
            ->toArray();
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
                'data'    => ['team_unread' => (object) [], 'unclaimed' => 0],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Agent chat notifications retrieved successfully',
            'data'    => $this->handle($user->chatAgent),
        ]);
    }
}
