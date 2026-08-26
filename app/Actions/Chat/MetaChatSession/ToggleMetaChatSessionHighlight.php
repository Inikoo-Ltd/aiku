<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleMetaChatSessionHighlight
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent): MetaChatSession
    {
        $highlight = !$metaChatSession->is_highlighted;

        $metaChatSession->update([
            'is_highlighted'          => $highlight,
            'highlighted_at'          => $highlight ? now() : null,
            'highlighted_by_agent_id' => $highlight ? $agent->id : null,
        ]);

        BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

        return $metaChatSession->fresh();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can highlight chats'),
            ], 403);
        }

        $metaChatSession = $this->handle($metaChatSession, $agent);

        return response()->json([
            'success' => true,
            'message' => $metaChatSession->is_highlighted ? __('Chat highlighted') : __('Highlight removed'),
            'data'    => [
                'session_ulid'   => $metaChatSession->ulid,
                'is_highlighted' => (bool) $metaChatSession->is_highlighted,
            ],
        ]);
    }
}
