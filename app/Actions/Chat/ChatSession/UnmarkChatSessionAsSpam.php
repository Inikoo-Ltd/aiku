<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UnmarkChatSessionAsSpam
{
    use AsAction;

    /**
     * Clear the spam flag so the session returns to its normal inbox tab.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $agent) {
            $updateData = [
                'is_spam'             => false,
                'spam_at'             => null,
                'spammed_by_agent_id' => null,
            ];


            $hasActiveAssignment = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            if (!$hasActiveAssignment && $chatSession->status !== ChatSessionStatusEnum::CLOSED) {
                $updateData['status'] = ChatSessionStatusEnum::WAITING->value;
            }

            $chatSession->update($updateData);

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::NOT_SPAM,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'action_type'       => 'not_spam',
                    'unspammed_by_id'   => $agent->id,
                    'unspammed_by_name' => $agent->user?->contact_name,
                    'unspammed_at'      => now()->toISOString(),
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
                'message' => 'Only authenticated agents can update spam status',
            ], 403);
        }

        if (!$chatSession->is_spam) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session is not marked as spam',
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
            'message' => 'Chat removed from spam',
            'data'    => [
                'session_ulid' => $chatSession->ulid,
                'is_spam'      => false,
                'action_type'  => 'not_spam',
            ],
        ]);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }
}
