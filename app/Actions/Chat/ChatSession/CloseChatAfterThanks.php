<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\UpdateShopChatClosing;
use App\Actions\Chat\WithBlockingTickets;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An email whose customer only thanked us is left waiting for the customer for the shop's hours,
 * nothing sent; the assistant may propose closing it as done. Website chat and WhatsApp are
 * never closed here: only the agent closes those.
 */
class CloseChatAfterThanks
{
    use AsAction;
    use WithBlockingTickets;

    public int $jobTimeout = 60;
    public int $jobTries = 1;

    private const array WRITERS = [ChatSenderTypeEnum::AGENT, ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST];

    public static function closeNowOrLater(ChatSession $chatSession): void
    {
        if ($chatSession->channel !== ChatChannelEnum::EMAIL) {
            return;
        }

        WaitForCustomerReply::run($chatSession, UpdateShopChatClosing::waitingHours($chatSession->shop), 'thanks');
    }

    /**
     * The thanks on an email chat an assistant may close as done, or why not: it is the customer's
     * latest message, it follows a reply from us, it is short, asks nothing and names no order, the
     * chat waits on no ticket or task, and, unless $untouchedOnly is false, nobody holds the chat.
     * ponytail: length, a question mark or an order-like token refuses; a false refusal only leaves the chat for staff.
     *
     * @return array{0: ChatMessage|null, 1: string|null}
     */
    public static function thanksToClose(ChatSession $chatSession, bool $untouchedOnly = true): array
    {
        if ($chatSession->channel !== ChatChannelEnum::EMAIL) {
            return [null, __('Only an email chat is closed this way.')];
        }

        if ($chatSession->status === ChatSessionStatusEnum::CLOSED) {
            return [null, __('This chat is already closed.')];
        }

        $blockers = self::make()->unresolvedBlockers($chatSession);
        if ($blockers->isNotEmpty()) {
            return [null, self::make()->blockersMessage($blockers)];
        }

        if ($untouchedOnly && $chatSession->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists()) {
            return [null, __('Somebody is holding this chat, so it is theirs to close.')];
        }

        [$thanks, $previous] = array_pad($chatSession->messages()
            ->whereIn('sender_type', array_map(fn (ChatSenderTypeEnum $sender) => $sender->value, self::WRITERS))
            ->latest('id')
            ->limit(2)
            ->get()
            ->all(), 2, null);

        if (!$thanks || $thanks->sender_type === ChatSenderTypeEnum::AGENT) {
            return [null, __('The customer has not written since our last reply.')];
        }

        if ($previous?->sender_type !== ChatSenderTypeEnum::AGENT) {
            return [null, __('The customer\'s last message does not follow a reply from us.')];
        }

        $text = trim((string) $thanks->message_text);
        if (mb_strlen($text) > 120 || preg_match('/[?？¿]/u', $text) || preg_match('/\b[A-Z]{2,}[-\/]?\d{3,}\b|\d{5,}/', $text)) {
            return [null, __('The customer\'s last message may ask for something, so it is left for staff.')];
        }

        return [$thanks, null];
    }

    /**
     * Closes as done, the way a website chat closes after a thanks, an email the assistant read as
     * only a thanks. Nothing is sent to the customer.
     *
     * @throws ValidationException
     */
    public static function closeForAssistant(ChatSession $chatSession, bool $untouchedOnly = true): void
    {
        [$thanks, $refused] = self::thanksToClose($chatSession, $untouchedOnly);

        if ($refused) {
            throw ValidationException::withMessages(['chat_id' => $refused]);
        }

        static::run($chatSession, $thanks->id);

        if ($chatSession->refresh()->status !== ChatSessionStatusEnum::CLOSED) {
            throw ValidationException::withMessages(['chat_id' => __('The chat could not be closed.')]);
        }
    }

    public function handle(ChatSession $chatSession, int $thanksId): void
    {
        $chatSession->refresh();

        if ($chatSession->status === ChatSessionStatusEnum::CLOSED || $this->writtenSince($chatSession, $thanksId)) {
            return;
        }

        CloseChatSession::run($chatSession, null, ChatActorTypeEnum::SYSTEM, ['reason' => 'only_thanks']);
        $chatSession->messages()->create([
            'message_text' => 'Closed automatically: the customer only thanked us. Anything they write next reopens it.',
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
            'is_read'      => true,
            'read_at'      => now(),
            'delivered_at' => now(),
            'metadata'     => ['automated' => ChatAutomationKindEnum::THANKS_CLOSED->value],
        ]);
    }

    private function writtenSince(ChatSession $chatSession, int $thanksId): bool
    {
        return $chatSession->messages()
            ->where('id', '>', $thanksId)
            ->whereIn('sender_type', array_map(fn (ChatSenderTypeEnum $sender) => $sender->value, self::WRITERS))
            ->exists();
    }
}
