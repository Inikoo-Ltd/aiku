<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TrashChatSession
{
    use AsAction;

    /**
     * Soft-delete the session (moves it to Trash). The chat is closed first so a
     * trashed — and any later restored — session is left in a clean, ended state
     * rather than lingering as active/waiting. SoftDeletes then hides it from every
     * normal query; it can be restored or permanently deleted from the Trash view.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ?int $actorId = null): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $actorId) {
            $hasActiveAgent = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            // Only close a chat that an agent is actually handling, so it stays Closed
            // when restored. A waiting chat (no agent yet) is trashed as-is and returns
            // to the Waiting queue on restore.
            if ($hasActiveAgent && !$chatSession->isClosed()) {
                CloseChatSession::run($chatSession, $actorId, ChatActorTypeEnum::AGENT);
            }

            BroadcastChatListEvent::dispatch(null, $chatSession);

            $chatSession->delete();

            return $chatSession;
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent instanceof ChatAgent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can trash chats'], 403);
        }

        try {
            $chatSession = $this->handle($chatSession, $agent->id);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat moved to trash',
            'data'    => ['session_ulid' => $chatSession->ulid],
        ]);
    }
}
