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
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A product the assistant showed the customer in the last 24 hours and that they put in the basket
 * is recorded as `added` once per product per conversation, on the conversation that showed it last:
 * from a card (single add) or as a new line of a bulk add. A quantity raised on such a line in the
 * basket is recorded as `increased`, once per product per conversation, and never counts as an add.
 * The storefront requests call afterResponse(): nothing is read while the website's switch is off,
 * and the gate, the lookups and the writes run after the response, where a failure is only reported.
 *
 * ponytail: the 24 hour window from the latest shown event is a deliberate simple rule; move it to
 * config or attribute by session when the reports need it.
 */
class RecordShoppingAssistantBasketAdd
{
    use AsAction;

    public function handle(Customer $customer, Product $product, ?int $transactionId, int|float|string $quantity, ShoppingAssistantEventTypeEnum $type = ShoppingAssistantEventTypeEnum::ADDED): void
    {
        $this->record($customer, [['product_id' => $product->id, 'transaction_id' => $transactionId, 'quantity' => $quantity]], $type);
    }

    /**
     * @param  array<int, array{product_id: int, transaction_id: ?int, quantity: int|float|string}>  $lines
     */
    public static function afterResponse(Website $website, Customer $customer, array $lines, ShoppingAssistantEventTypeEnum $type = ShoppingAssistantEventTypeEnum::ADDED): void
    {
        if (!$lines || !data_get($website->settings, 'shopping_assistant.enabled')) {
            return;
        }

        logAfterResponse(function () use ($website, $customer, $lines, $type) {
            if (GetShoppingAssistantSettings::run($website, $customer)) {
                static::make()->record($customer, $lines, $type);
            }
        });
    }

    /**
     * One lookup for the latest showing of every product, one for the events of this type already
     * recorded on those conversations, then one insert.
     *
     * @param  array<int, array{product_id: int, transaction_id: ?int, quantity: int|float|string}>  $lines
     */
    public function record(Customer $customer, array $lines, ShoppingAssistantEventTypeEnum $type): void
    {
        $lines = collect($lines)->keyBy('product_id');

        $shown = DB::table('shopping_assistant_events')
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_events.shopping_assistant_conversation_id')
            ->where('shopping_assistant_conversations.customer_id', $customer->id)
            ->where('shopping_assistant_events.type', ShoppingAssistantEventTypeEnum::SHOWN->value)
            ->whereIn('shopping_assistant_events.product_id', $lines->keys()->all())
            ->where('shopping_assistant_events.created_at', '>=', now()->subDay())
            ->orderBy('shopping_assistant_events.product_id')
            ->orderByDesc('shopping_assistant_events.id')
            ->selectRaw('distinct on (shopping_assistant_events.product_id) shopping_assistant_events.*')
            ->get();

        if ($shown->isEmpty()) {
            return;
        }

        $recorded = DB::table('shopping_assistant_events')
            ->whereIn('shopping_assistant_conversation_id', $shown->pluck('shopping_assistant_conversation_id')->unique()->all())
            ->whereIn('product_id', $shown->pluck('product_id')->all())
            ->where('type', $type->value)
            ->get(['shopping_assistant_conversation_id', 'product_id'])
            ->map(fn ($event) => $event->shopping_assistant_conversation_id.':'.$event->product_id)
            ->flip();

        $rows = $shown
            ->reject(fn ($event) => $recorded->has($event->shopping_assistant_conversation_id.':'.$event->product_id))
            ->map(fn ($event) => [
                'shopping_assistant_conversation_id' => $event->shopping_assistant_conversation_id,
                'shopping_assistant_message_id'      => $event->shopping_assistant_message_id,
                'type'                               => $type->value,
                'product_id'                         => $event->product_id,
                'group'                              => $event->group,
                'position'                           => $event->position,
                'reason_code'                        => $event->reason_code,
                'transaction_id'                     => $lines[$event->product_id]['transaction_id'],
                'quantity'                           => $lines[$event->product_id]['quantity'],
                'created_at'                         => now(),
            ])
            ->values()
            ->all();

        if ($rows) {
            ShoppingAssistantEvent::insert($rows);
        }
    }
}
