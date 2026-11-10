<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A product the assistant showed the customer in the last 24 hours and that they put in the basket
 * is recorded once per product per conversation, on the conversation that showed it last. Quantity
 * changes from the cards reach the same basket route and record nothing more.
 *
 * ponytail: the 24 hour window from the latest shown event is a deliberate simple rule; move it to
 * config or attribute by session when the reports need it.
 */
class RecordShoppingAssistantBasketAdd
{
    use AsAction;

    public function handle(Customer $customer, Product $product, ?int $transactionId, int|float|string $quantity): void
    {
        $shown = ShoppingAssistantEvent::query()
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_events.shopping_assistant_conversation_id')
            ->where('shopping_assistant_conversations.customer_id', $customer->id)
            ->where('shopping_assistant_events.type', ShoppingAssistantEventTypeEnum::SHOWN)
            ->where('shopping_assistant_events.product_id', $product->id)
            ->where('shopping_assistant_events.created_at', '>=', now()->subDay())
            ->orderByDesc('shopping_assistant_events.id')
            ->select('shopping_assistant_events.*')
            ->first();

        if (!$shown || ShoppingAssistantEvent::where('shopping_assistant_conversation_id', $shown->shopping_assistant_conversation_id)->where('type', ShoppingAssistantEventTypeEnum::ADDED)->where('product_id', $product->id)->exists()) {
            return;
        }

        ShoppingAssistantEvent::create([
            'shopping_assistant_conversation_id' => $shown->shopping_assistant_conversation_id,
            'shopping_assistant_message_id'      => $shown->shopping_assistant_message_id,
            'type'                               => ShoppingAssistantEventTypeEnum::ADDED,
            'product_id'                         => $product->id,
            'group'                              => $shown->group,
            'position'                           => $shown->position,
            'reason_code'                        => $shown->reason_code,
            'transaction_id'                     => $transactionId,
            'quantity'                           => $quantity,
        ]);
    }
}
