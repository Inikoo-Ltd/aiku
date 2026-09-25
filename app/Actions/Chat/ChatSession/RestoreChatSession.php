<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use Illuminate\Support\Facades\Auth;

class RestoreChatSession
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * Restore a trashed session. With no active agent it re-enters the Waiting
     * queue (unless it was closed), mirroring the un-spam behaviour.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ?int $actorId = null): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $actorId) {
            $chatSession->restore();

            StoreChatEvent::run(
                $chatSession,
                ChatEventTypeEnum::RESTORE,
                ChatActorTypeEnum::AGENT,
                $actorId,
                ['user_id' => Auth::id()]
            );

            $hasActiveAssignment = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            if (!$hasActiveAssignment && $chatSession->status !== ChatSessionStatusEnum::CLOSED) {
                $chatSession->update(['status' => ChatSessionStatusEnum::WAITING->value]);
            }

            BroadcastChatListEvent::dispatch(null, $chatSession);

            return $chatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent instanceof ChatAgent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can restore chats'], 403);
        }

        try {
            $chatSession = $this->handle($chatSession, $agent->id);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat restored',
            'data'    => ['session_ulid' => $chatSession->ulid],
        ]);
    }
}
