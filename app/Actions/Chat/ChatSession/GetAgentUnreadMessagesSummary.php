<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentUnreadMessagesSummary
{
    use AsAction;

    public function handle(ChatAgent $agent): array
    {
        $shopIds = $agent->shops()->pluck('shops.id');

        if ($shopIds->isEmpty()) {
            return [
                'assigned_unread_count'   => 0,
                'unassigned_unread_count' => 0,
                'total_unread_count'      => 0,
            ];
        }

        $visitorSenderTypes = [
            ChatSenderTypeEnum::GUEST->value,
            ChatSenderTypeEnum::USER->value,
        ];

        $assignedUnreadCount = ChatMessage::query()
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
            ->count();

        $unassignedUnreadCount = ChatMessage::query()
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
            ->count();



        // The badge is one number over every channel the agent works in: a WhatsApp
        // customer waiting is no less urgent than a website one, and splitting the count
        // would mean the rail could read zero while someone is still unanswered.
        $whatsapp = $this->whatsappCounts($agent, $shopIds);

        return [
            'assigned_unread_count'   => $assignedUnreadCount + $whatsapp['assigned'],
            'unassigned_unread_count' => $unassignedUnreadCount + $whatsapp['unassigned'],
            'total_unread_count'      => $assignedUnreadCount + $unassignedUnreadCount
                + $whatsapp['assigned'] + $whatsapp['unassigned'],
            'by_channel'              => [
                'website'  => [
                    'assigned'   => $assignedUnreadCount,
                    'unassigned' => $unassignedUnreadCount,
                ],
                'whatsapp' => [
                    'assigned'   => $whatsapp['assigned'],
                    'unassigned' => $whatsapp['unassigned'],
                ],
            ],
        ];
    }

    /**
     * @return array{assigned: int, unassigned: int}
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
            })
            ->count();

        $unassigned = MetaChatMessage::query()
            ->where('is_read', false)
            ->where('sender_type', ChatSenderTypeEnum::GUEST->value)
            ->whereHas('metaChatSession', function ($query) use ($shopIds) {
                $query->where('status', ChatSessionStatusEnum::WAITING->value)
                    ->where('is_spam', false)
                    ->whereIn('shop_id', $shopIds)
                    ->whereDoesntHave('assignments', function ($assignmentQuery) {
                        $assignmentQuery->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
                    });
            })
            ->count();

        return ['assigned' => $assigned, 'unassigned' => $unassigned];
    }

    public function asController(ActionRequest $request, $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user || !$user->chatAgent) {
            return response()->json([
                'success' => true,
                'message' => 'User is not a chat agent',
                'data' => [
                    'assigned_unread_count' => 0,
                    'unassigned_unread_count' => 0,
                    'total_unread_count' => 0,
                ],
            ]);
        }

        $summary = $this->handle($user->chatAgent);

        return response()->json([
            'success' => true,
            'message' => 'Unread message summary retrieved successfully',
            'data' => $summary,
        ]);
    }
}
