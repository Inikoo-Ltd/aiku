<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\SendChatAiAnswer;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff saying one of the fixed messages the chat sent on its own, in either channel, was wrong.
 * It stays recorded as sent, only marked, the same flag an AI draft carries.
 */
class FlagChatAutomatedMessage
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(ChatMessage|MetaChatMessage $message, int $userId): ChatMessage|MetaChatMessage
    {
        if (!Arr::get($message->metadata, 'flagged_wrong_at')) {
            $message->update(['metadata' => array_merge($message->metadata ?? [], [
                'flagged_wrong_at'   => now()->toISOString(),
                'flagged_by_user_id' => $userId,
            ])]);
        }

        return $message;
    }

    public function asController(string $channel, int $messageId, ActionRequest $request): RedirectResponse
    {
        $message = $channel === 'whatsapp' ? MetaChatMessage::findOrFail($messageId) : ChatMessage::findOrFail($messageId);

        abort_unless($this->isFlaggable($message, $channel, $request), 404);

        $this->handle($message, $request->user()->id);

        return back();
    }

    /**
     * A message can only be flagged when it is one the chat sent on its own: an AI-written
     * reply carries its own flag through the draft, so it is left out here.
     */
    private function isFlaggable(ChatMessage|MetaChatMessage $message, string $channel, ActionRequest $request): bool
    {
        if ($message->sender_type !== ChatSenderTypeEnum::SYSTEM) {
            return false;
        }

        $session = $channel === 'whatsapp' ? $message->metaChatSession : $message->chatSession;

        if (!$session?->shop || $session->shop->group_id !== $request->user()->group_id) {
            return false;
        }

        if ($channel === 'whatsapp') {
            return (bool) Arr::first(
                ['claim_details_asked_at', 'out_of_hours_replied_at', 'asked_if_customer', 'greeted_at', 'greeting'],
                fn (string $key) => Arr::get($message->metadata, $key)
            );
        }

        $automated = Arr::get($message->metadata, 'automated');

        return $automated && $automated !== SendChatAiAnswer::MESSAGE_MARKER;
    }
}
