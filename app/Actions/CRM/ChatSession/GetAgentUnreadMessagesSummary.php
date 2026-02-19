<?php

namespace App\Actions\CRM\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentUnreadMessagesSummary
{
    use AsAction;

    public function handle(ChatAgent $agent): array
    {
        $shopIds = $agent->shops()->pluck('shops.id');

        if ($shopIds->isEmpty()) {
            return [
                'assigned_unread_count' => 0,
                'unassigned_unread_count' => 0,
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
                    ->whereIn('shop_id', $shopIds)
                    ->whereDoesntHave('assignments');
            })
            ->count();



        return [
            'assigned_unread_count' => $assignedUnreadCount,
            'unassigned_unread_count' => $unassignedUnreadCount,
            'total_unread_count' => $assignedUnreadCount + $unassignedUnreadCount,
        ];
    }

    public function asController(): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->chatAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can check unread messages',
            ], 403);
        }

        $summary = $this->handle($user->chatAgent);

        return response()->json([
            'success' => true,
            'message' => 'Unread message summary retrieved successfully',
            'data' => $summary,
        ]);
    }
}
