<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RestoreChatSession
{
    use AsAction;

    /**
     * Restore a trashed session. With no active agent it re-enters the Waiting
     * queue (unless it was closed), mirroring the un-spam behaviour.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession): ChatSession
    {
        return DB::transaction(function () use ($chatSession) {
            $chatSession->restore();

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
        if (!Auth::user()?->chatAgent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can restore chats'], 403);
        }

        try {
            $chatSession = $this->handle($chatSession);
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
