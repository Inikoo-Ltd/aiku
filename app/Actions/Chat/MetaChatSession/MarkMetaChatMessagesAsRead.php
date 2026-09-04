<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\Whatsapp\SendWhatsappReadReceipt;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkMetaChatMessagesAsRead
{
    use AsAction;

    public function rules(): array
    {
        return [
            'meta_chat_message_id' => ['sometimes', 'integer', 'exists:meta_chat_messages,id'],
        ];
    }

    /**
     * Marks the customer's messages as read on our side and tells WhatsApp about it,
     * so the customer sees blue ticks. Without a message the whole thread is marked,
     * which is what opening a conversation means.
     *
     * @return int the number of messages marked
     */
    public function handle(MetaChatSession $metaChatSession, ?MetaChatMessage $metaChatMessage = null): int
    {
        $query = $metaChatSession->messages()
            ->where('is_read', false)
            ->whereIn('sender_type', [
                ChatSenderTypeEnum::GUEST->value,
                ChatSenderTypeEnum::USER->value,
            ]);

        if ($metaChatMessage) {
            $query->whereKey($metaChatMessage->id);
        }

        $unread = $query->get();

        if ($unread->isEmpty()) {
            return 0;
        }

        $metaChatSession->messages()
            ->whereIn('id', $unread->pluck('id'))
            ->update(['is_read' => true, 'read_at' => now()]);

        // One receipt for the newest message covers everything before it.
        SendWhatsappReadReceipt::run($unread->sortByDesc('created_at')->first());

        BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

        return $unread->count();
    }

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): int
    {
        $messageId = $request->validated('meta_chat_message_id');

        return $this->handle(
            $metaChatSession,
            $messageId ? $metaChatSession->messages()->findOrFail($messageId) : null
        );
    }

    public function jsonResponse(int $marked): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['marked' => $marked],
        ]);
    }
}
