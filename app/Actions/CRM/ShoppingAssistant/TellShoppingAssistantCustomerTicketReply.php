<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Tasks\GetAikuAssistant;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A comment staff mark "to the customer" on a ticket Merlin raised: the customer cannot see tickets
 * yet (MER-02), so it is translated from English into the conversation's language, added to their
 * Merlin conversation and emailed with a link to it, or customer services is asked to pass it on.
 * A failure is logged and never fails the comment.
 */
class TellShoppingAssistantCustomerTicketReply
{
    use AsAction;

    public function handle(Ticket $ticket, string $body): ?string
    {
        $body = trim($body);
        if ($body === '' || $ticket->source_type !== 'ShoppingAssistantConversation') {
            return null;
        }

        $conversation = ShoppingAssistantConversation::with('customer.shop', 'website')->find($ticket->source_id);
        if (!$conversation?->customer || $conversation->wiped_at) {
            return null;
        }

        try {
            $followUp   = FollowUpShoppingAssistantRestockQuestion::make();
            $translated = $followUp->translate($body, $conversation->language_code);
            $message    = __('Our team answered your question:', [], $conversation->language_code)."\n\n".$translated;

            $conversation->messages()->create([
                'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
                'body'  => $message,
                'model' => 'team',
                'flags' => ['ticket_reply'],
            ]);
            $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

            [$channel] = $followUp->deliver(
                $conversation,
                $message,
                $translated,
                $body,
                __('Our engineers answered :reference, a problem this customer reported to the AI shopping assistant. The shop cannot email it automatically, so please send it to the customer.', ['reference' => $ticket->reference]),
                ['ticket_id' => $ticket->id],
                GetAikuAssistant::run($ticket->group_id)
            );

            return $channel;
        } catch (Throwable $exception) {
            Log::error('MER reply to customer failed on '.$ticket->reference.': '.$exception->getMessage());

            return null;
        }
    }
}
