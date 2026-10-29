<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastRealtimeChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An email reply Gmail refused on every try, sent again from the conversation once the agent has
 * put right what stopped it, usually a mailbox that needed reconnecting (INI-144).
 */
class ResendChatMessageByGmail
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * Claimed by clearing the failure in the same statement that checks it is still there, so two
     * clicks (two tabs, two colleagues) send it once, not twice.
     */
    public function handle(ChatMessage $chatMessage): bool
    {
        $claimed = ChatMessage::whereKey($chatMessage->id)
            ->whereNotNull('metadata->email_send_failed')
            ->update([
                'metadata' => json_encode(array_merge(Arr::except($chatMessage->metadata ?? [], 'email_send_failed'), [
                    'email_resent_at' => now()->toISOString(),
                ])),
            ]);

        if (! $claimed) {
            return false;
        }

        $chatMessage->refresh();

        BroadcastRealtimeChat::dispatch($chatMessage);

        SendChatMessageByGmail::dispatch($chatMessage);

        return true;
    }

    public function asController(string $organisation, ChatSession $chatSession, ChatMessage $chatMessage): JsonResponse
    {
        if (! $this->getAuthorisedChatAgent($chatSession) instanceof ChatAgent
            || ! $this->userCanDisposeOfChat(request()->user(), $chatSession)) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents of this shop can send its emails'),
            ], 403);
        }

        if ($chatMessage->chat_session_id !== $chatSession->id
            || $chatSession->channel !== ChatChannelEnum::EMAIL
            || $chatMessage->sender_type !== ChatSenderTypeEnum::AGENT) {
            return response()->json([
                'success' => false,
                'message' => __('This is not an email reply of this conversation'),
            ], 422);
        }

        if (Arr::get($chatMessage->metadata, 'gmail_message_id')) {
            return response()->json([
                'success' => false,
                'message' => __('This email was already sent'),
            ], 422);
        }

        if (! $this->handle($chatMessage)) {
            return response()->json([
                'success' => false,
                'message' => __('This email is already being sent'),
            ], 422);
        }

        return response()->json(['success' => true]);
    }
}
