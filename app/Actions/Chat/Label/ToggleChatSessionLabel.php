<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Label;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatLabel;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleChatSessionLabel
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession $chatSession, ChatLabel $label): bool
    {
        $isOn = $chatSession->labels()->whereKey($label->id)->exists();

        if ($isOn) {
            $chatSession->labels()->detach($label->id);
        } else {
            $chatSession->labels()->attach($label->id, ['created_at' => now()]);
        }

        BroadcastChatListEvent::dispatch(null, $chatSession);

        return !$isOn;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ChatLabel $chatLabel): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession) || $chatLabel->organisation_id !== $chatSession->shop?->organisation_id) {
            return response()->json(['success' => false, 'message' => __('You may not label this conversation')], 403);
        }

        $isOn = $this->handle($chatSession, $chatLabel);

        return response()->json([
            'success' => true,
            'data'    => [
                'session_ulid' => $chatSession->ulid,
                'labels'       => $chatSession->labels()->orderBy('name')->get(['chat_labels.id', 'name', 'colour'])->map(fn (ChatLabel $label) => $label->only(['id', 'name', 'colour'])),
                'is_on'        => $isOn,
            ],
        ]);
    }
}
