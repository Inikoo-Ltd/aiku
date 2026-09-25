<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\SendMetaChatGreeting;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A guest who writes like somebody who already buys from us, and whom nothing we hold
 * identifies, is asked once for the email on their account or an order number. The words are
 * fixed and sent as the system; what comes back is looked up like anything else they wrote and
 * still only ever suggests. Never by email: an automatic reply there talks to auto-responders.
 */
class AskGuestIfCustomer
{
    use AsAction;

    public function handle(ChatSession|MetaChatSession $chatSession): bool
    {
        $byEmail = $chatSession instanceof ChatSession && $chatSession->channel === ChatChannelEnum::EMAIL;

        if ($byEmail
            || !config('chat.ask_guest_if_customer')
            || $chatSession->customer_asked_at
            || $chatSession->last_agent_message_at
            || !SuggestChatSessionCustomer::isOpenToSuggestion($chatSession)) {
            return false;
        }

        $chatSession->update(['customer_asked_at' => now()]);

        $text = __('If you already buy from us, please send the email on your account or an order number, so we can find you faster.', [], $chatSession->shop?->language?->code);

        if ($chatSession instanceof MetaChatSession) {
            return SendMetaChatGreeting::run($chatSession, $text, 'asked_if_customer');
        }

        $asked = SendChatMessage::run($chatSession, [
            'message_text' => $text,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
        ]);

        $asked->update(['metadata' => array_merge($asked->metadata ?? [], ['automated' => ChatAutomationKindEnum::ASKED_IF_CUSTOMER->value])]);

        return true;
    }
}
