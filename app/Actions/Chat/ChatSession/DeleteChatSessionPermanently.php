<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatEvent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteChatSessionPermanently
{
    use AsAction;

    /**
     * Permanently remove the session and its children. Message translations are
     * cascaded by the DB when their message rows are deleted.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession): void
    {
        DB::transaction(function () use ($chatSession) {
            ChatMessage::withTrashed()->where('chat_session_id', $chatSession->id)->forceDelete();
            ChatAssignment::where('chat_session_id', $chatSession->id)->delete();
            ChatEvent::where('chat_session_id', $chatSession->id)->delete();

            $chatSession->forceDelete();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        if (!Auth::user()?->chatAgent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can delete chats'], 403);
        }

        $ulid = $chatSession->ulid;

        try {
            $this->handle($chatSession);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat permanently deleted',
            'data'    => ['session_ulid' => $ulid],
        ]);
    }
}
