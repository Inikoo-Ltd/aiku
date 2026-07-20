<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Events\BroadcastRealtimeChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReopenChatSession
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $agent) {
            $previousStatus = $chatSession->status?->value;

            $chatSession->update([
                'status'    => ChatSessionStatusEnum::ACTIVE->value,
                'closed_by' => null,
                'closed_at' => null,
            ]);

            $assignment = $this->assignReopeningAgent($chatSession, $agent);

            $systemMessage = $chatSession->messages()->create([
                'message_text' => 'Chat session has been reopened by '.($agent->user?->contact_name ?? 'agent'),
                'message_type' => ChatMessageTypeEnum::TEXT->value,
                'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
                'is_read'      => true,
                'read_at'      => now(),
                'delivered_at' => now(),
            ]);

            BroadcastRealtimeChat::dispatch($systemMessage);

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::REOPEN,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'action_type'             => 'reopen',
                    'assignment_id'           => $assignment->id,
                    'assigned_agent_id'       => $agent->id,
                    'assigned_agent_name'     => $agent->user?->contact_name,
                    'session_previous_status' => $previousStatus,
                    'session_new_status'      => ChatSessionStatusEnum::ACTIVE->value,
                    'reopened_at'             => now()->toISOString(),
                ]
            );

            BroadcastChatListEvent::dispatch();

            return $chatSession->fresh();
        });
    }

    private function assignReopeningAgent(ChatSession $chatSession, ChatAgent $agent): ChatAssignment
    {
        /** @var ChatAssignment|null $activeAssignment */
        $activeAssignment = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        if ($activeAssignment) {
            if ($activeAssignment->chat_agent_id !== $agent->id) {
                $previousAgent = $activeAssignment->chatAgent;

                $activeAssignment->update([
                    'chat_agent_id' => $agent->id,
                    'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
                    'note'          => 'Reopened by agent',
                    'assigned_at'   => now(),
                ]);

                if ($previousAgent) {
                    ChatAgentHydrateChats::run($previousAgent);
                }
            }

            ChatAgentHydrateChats::run($agent);

            return $activeAssignment;
        }

        /** @var ChatAssignment $assignment */
        $assignment = $chatSession->assignments()->create([
            'chat_agent_id' => $agent->id,
            'status'        => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by'   => ChatAssignmentAssignedByEnum::AGENT->value,
            'note'          => 'Reopened by agent',
            'assigned_at'   => now(),
        ]);

        ChatAgentHydrateChats::run($agent);

        return $assignment;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can reopen chats',
            ], 403);
        }

        if (!$chatSession->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session is not closed',
            ], 422);
        }

        try {
            $chatSession = $this->handle($chatSession, $agent);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat reopened successfully',
            'data'    => [
                'session_ulid'        => $chatSession->ulid,
                'session_status'      => $chatSession->status->value,
                'assigned_agent_id'   => $agent->id,
                'assigned_agent_name' => $agent->user?->contact_name ?? 'Unknown',
                'action_type'         => 'reopen',
            ],
        ]);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }
}
