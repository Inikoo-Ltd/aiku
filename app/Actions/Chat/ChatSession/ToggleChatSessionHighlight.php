<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleChatSessionHighlight
{
    use AsAction;

    public function handle(ChatSession $chatSession, ChatAgent $agent): ChatSession
    {
        $highlight = !$chatSession->is_highlighted;

        $chatSession->update([
            'is_highlighted'          => $highlight,
            'highlighted_at'          => $highlight ? now() : null,
            'highlighted_by_agent_id' => $highlight ? $agent->id : null,
        ]);

        BroadcastChatListEvent::dispatch(null, $chatSession);

        return $chatSession->fresh();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can highlight chats',
            ], 403);
        }

        $chatSession = $this->handle($chatSession, $agent);

        return response()->json([
            'success' => true,
            'message' => $chatSession->is_highlighted ? 'Chat highlighted' : 'Highlight removed',
            'data'    => [
                'session_ulid'   => $chatSession->ulid,
                'is_highlighted' => (bool) $chatSession->is_highlighted,
            ],
        ]);
    }
}
