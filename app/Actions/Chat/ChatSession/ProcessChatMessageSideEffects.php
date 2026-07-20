<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
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
        $this->fireOfflineAutomations($chatSession, $senderType);
    }

    private function fireOfflineAutomations(ChatSession $chatSession, string $senderType): void
    {
        $isVisitorMessage = \in_array($senderType, [
            ChatSenderTypeEnum::GUEST->value,
            ChatSenderTypeEnum::USER->value,
        ]);

        if (! $isVisitorMessage) {
            return;
        }

        try {
            $website = $chatSession->shop?->website;
            if (! $website) {
                return;
            }

            $config = \App\Actions\HumanResources\WorkSchedule\GetChatConfig::run($website);
            if (($config['is_online'] ?? false) === true) {
                return;
            }

            \App\Actions\CRM\ChatAutomation\ResolveChatAutomations::run(
                $chatSession,
                \App\Enums\CRM\Livechat\ChatAutomationTriggerEnum::OFFLINE
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Offline automation failed', [
                'chat_session_id' => $chatSession->id,
                'error'           => $e->getMessage(),
            ]);
        }
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
