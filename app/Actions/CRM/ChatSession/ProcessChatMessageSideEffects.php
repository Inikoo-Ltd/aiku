<?php

namespace App\Actions\CRM\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessChatMessageSideEffects
{
    use AsAction;

    public int $jobTimeout = 60;
    public int $jobTries   = 3;

    public function handle(ChatSession $chatSession, string $senderType, ?int $senderId, ChatMessage $chatMessage): void
    {
        $this->updateSessionTimestamps($chatSession, $senderType);
        $this->logMessageEvent($chatSession, $senderType, $senderId, $chatMessage);
    }

    private function updateSessionTimestamps(ChatSession $chatSession, string $senderType): void
    {
        $updateData = [];

        if (\in_array($senderType, [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])) {
            $updateData['last_visitor_message_at'] = now();
        } elseif ($senderType === ChatSenderTypeEnum::AGENT->value) {
            $updateData['last_agent_message_at'] = now();
        }

        if (!empty($updateData)) {
            $chatSession->update($updateData);
        }
    }

    private function logMessageEvent(ChatSession $chatSession, string $senderType, ?int $senderId, ChatMessage $chatMessage): void
    {
        $actorType = match ($senderType) {
            ChatActorTypeEnum::AGENT->value => ChatActorTypeEnum::AGENT,
            ChatSenderTypeEnum::USER->value  => ChatActorTypeEnum::USER,
            default                          => ChatActorTypeEnum::GUEST,
        };

        $isGuestMessage = \in_array($senderType, [
            ChatActorTypeEnum::GUEST->value,
            ChatActorTypeEnum::USER->value,
        ]);

        StoreChatEvent::make()->messageSent(
            $chatSession,
            $actorType,
            $senderId,
            $chatMessage->id,
            $chatMessage->message_type->value,
            $isGuestMessage
        );
    }
}
