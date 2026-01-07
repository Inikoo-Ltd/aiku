<?php

namespace App\Actions\CRM\ChatSession;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatSession;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastMessagesRead;

class MarkChatMessagesAsRead
{
    use AsAction;

    public function handle(ChatSession $chatSession, ChatSenderTypeEnum $readerType): void
    {
        $query = $chatSession->messages()
            ->where('is_read', false);

        if (in_array($readerType, [
            ChatSenderTypeEnum::GUEST,
            ChatSenderTypeEnum::USER,
        ])) {

            $query->whereNotIn('sender_type', [
                ChatSenderTypeEnum::GUEST->value,
                ChatSenderTypeEnum::USER->value,
            ]);
        } elseif ($readerType === ChatSenderTypeEnum::AGENT) {

            $query->whereIn('sender_type', [
                ChatSenderTypeEnum::GUEST->value,
                ChatSenderTypeEnum::USER->value,
            ]);
        }

        $messageIds = $query->pluck('id');

        if ($messageIds->isEmpty()) {
            return;
        }

        $chatSession->messages()
            ->whereIn('id', $messageIds)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        broadcast(
            new BroadcastMessagesRead(
                $chatSession,
                $messageIds->toArray(),
                $readerType->value
            )
        );
    }
}