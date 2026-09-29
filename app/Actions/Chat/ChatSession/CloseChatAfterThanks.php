<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\CloseMetaChatSession;
use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\UpdateShopChatClosing;
use App\Actions\Chat\Whatsapp\SendWhatsappReaction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatClosingAfterThanks;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer who only thanked us gets a 👍 on website chat and WhatsApp and the conversation
 * closes; an email is closed without anything sent. When an agent is in the chat it waits the
 * shop's minutes instead, with a countdown on their screen, and closes only if nobody has
 * written since the thanks and the agent did not start typing or click "Keep open".
 */
class CloseChatAfterThanks
{
    use AsAction;

    public int $jobTimeout = 60;
    public int $jobTries = 1;

    public const string REACTION = '👍';

    public const string PENDING_KEY = 'thanks_close';

    private const array WRITERS = [ChatSenderTypeEnum::AGENT, ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST];

    /**
     * An agent with the conversation open marks each customer message read as it arrives, so
     * a thanks already read means somebody is in the chat.
     * ponytail: read receipt as presence; an agent who left the chat open and walked away still counts as there.
     */
    public static function closeNowOrLater(ChatSession|MetaChatSession $chatSession): void
    {
        $thanks = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::USER->value, ChatSenderTypeEnum::GUEST->value])
            ->latest('id')
            ->first();

        if (!$thanks) {
            return;
        }

        if ($thanks->is_read) {
            $closingAt = now()->addMinutes(UpdateShopChatClosing::minutes($chatSession->shop));

            $chatSession->update(['metadata' => [...($chatSession->metadata ?? []), self::PENDING_KEY => ['message_id' => $thanks->id, 'at' => $closingAt->toISOString()]]]);
            BroadcastChatClosingAfterThanks::dispatch($chatSession, $closingAt->toISOString());
            static::dispatch($chatSession, $thanks->id, true)->delay($closingAt);

            return;
        }

        static::run($chatSession, $thanks->id);
    }

    public static function pendingAt(ChatSession|MetaChatSession $chatSession): ?string
    {
        return $chatSession->status === ChatSessionStatusEnum::CLOSED ? null : data_get($chatSession->metadata, self::PENDING_KEY.'.at');
    }

    public static function cancel(ChatSession|MetaChatSession $chatSession): void
    {
        $chatSession->refresh();
        $metadata = $chatSession->metadata ?? [];

        if (!array_key_exists(self::PENDING_KEY, $metadata)) {
            return;
        }

        unset($metadata[self::PENDING_KEY]);
        $chatSession->update(['metadata' => $metadata]);
        BroadcastChatClosingAfterThanks::dispatch($chatSession, null);
    }

    public function handle(ChatSession|MetaChatSession $chatSession, int $thanksId, bool $scheduled = false): void
    {
        $chatSession->refresh();

        if ($scheduled && data_get($chatSession->metadata, self::PENDING_KEY.'.message_id') !== $thanksId) {
            return;
        }

        if ($chatSession->status === ChatSessionStatusEnum::CLOSED || $this->writtenSince($chatSession, $thanksId)) {
            self::cancel($chatSession);

            return;
        }

        self::cancel($chatSession);

        $reacted = $this->react($chatSession, $thanksId);

        $note = [
            'message_text' => $reacted
                ? 'Closed automatically: the customer only thanked us, so we answered with a 👍. Anything they write next reopens it.'
                : 'Closed automatically: the customer only thanked us. Anything they write next reopens it.',
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
            'is_read'      => true,
            'read_at'      => now(),
            'delivered_at' => now(),
            'metadata'     => ['automated' => ChatAutomationKindEnum::THANKS_CLOSED->value],
        ];

        if ($chatSession instanceof MetaChatSession) {
            CloseMetaChatSession::run($chatSession, null, ChatActorTypeEnum::SYSTEM, ['reason' => 'only_thanks']);
            StoreMetaChatMessage::run($chatSession, $note);

            return;
        }

        CloseChatSession::run($chatSession, null, ChatActorTypeEnum::SYSTEM, ['reason' => 'only_thanks']);
        $chatSession->messages()->create($note);
    }

    private function writtenSince(ChatSession|MetaChatSession $chatSession, int $thanksId): bool
    {
        return $chatSession->messages()
            ->where('id', '>', $thanksId)
            ->whereIn('sender_type', array_map(fn (ChatSenderTypeEnum $sender) => $sender->value, self::WRITERS))
            ->exists();
    }

    private function react(ChatSession|MetaChatSession $chatSession, int $thanksId): bool
    {
        if ($chatSession instanceof ChatSession && $chatSession->channel === ChatChannelEnum::EMAIL) {
            return false;
        }

        $thanks = $chatSession->messages()->find($thanksId);

        if ($thanks instanceof MetaChatMessage) {
            return SendWhatsappReaction::run($thanks, null, self::REACTION)['ok'];
        }

        if ($thanks instanceof ChatMessage) {
            ToggleChatMessageReaction::run($thanks, ['reactor_type' => ChatSenderTypeEnum::AGENT->value, 'reactor_id' => null], self::REACTION);

            return true;
        }

        return false;
    }
}
