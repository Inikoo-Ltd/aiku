<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkChatSessionAsSpam
{
    use AsAction;

    /**
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $agent) {
            $chatSession->update([
                'is_spam'             => true,
                'spam_at'             => now(),
                'spammed_by_agent_id' => $agent->id,
            ]);

            $activeAssignments = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->get();

            foreach ($activeAssignments as $assignment) {
                $assignment->update([
                    'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                ]);

                $assignedAgent = ChatAgent::find($assignment->chat_agent_id);
                if ($assignedAgent) {
                    ChatAgentHydrateChats::run($assignedAgent);
                }
            }

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::SPAM,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'action_type'     => 'spam',
                    'spammed_by_id'   => $agent->id,
                    'spammed_by_name' => $agent->user?->contact_name,
                    'spammed_at'      => now()->toISOString(),
                ]
            );

            BroadcastChatListEvent::dispatch(null, $chatSession);

            return $chatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can mark chats as spam',
            ], 403);
        }

        if ($chatSession->is_spam) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session is already marked as spam',
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
            'message' => 'Chat marked as spam',
            'data'    => [
                'session_ulid' => $chatSession->ulid,
                'is_spam'      => true,
                'action_type'  => 'spam',
            ],
        ]);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }
}
