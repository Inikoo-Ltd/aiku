<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The pre-order panel shown on the order, to the customer in retina and to staff in grp.
 */
class GetPreOrderShowcase
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(PreOrder $preOrder): array
    {
        $order         = $preOrder->order;
        $isOpen        = in_array($preOrder->state, PreOrderStateEnum::open());

        return [
            'id'                       => $preOrder->id,
            'state'                    => $preOrder->state->value,
            'state_label'              => $preOrder->state->label(),
            'is_open'                  => $isOpen,
            'is_trade'                 => $preOrder->is_trade,
            'has_back_order'           => $preOrder->has_back_order,
            'has_made_to_order'        => $preOrder->has_made_to_order,
            'has_pallet_delivery'      => $preOrder->has_pallet_delivery,
            'order_reference'          => $order->reference,
            'parent_order_reference'   => $preOrder->parentOrder?->reference,
            'parent_order_slug'        => $preOrder->parentOrder?->slug,
            'estimated_dispatch_from'  => $preOrder->estimated_dispatch_from?->toDateString(),
            'estimated_dispatch_to'    => $preOrder->estimated_dispatch_to?->toDateString(),
            'is_late'                  => $preOrder->isLate(),
            'currency_code'            => $order->currency->code,
            'total_amount'             => (float) $order->total_amount,
            'paid_amount'              => (float) $order->payment_amount,
            'balance_amount'           => round(max(0, (float) $order->total_amount - (float) $order->payment_amount), 2),
            'made_to_order_deposit'    => (float) ($preOrder->data['made_to_order_deposit_amount'] ?? 0),
            'free_cancellation_until'  => $preOrder->isWithinFreeCancellation() ? $preOrder->free_cancellation_until?->toIso8601String() : null,
            'supplier_ordered_at'      => $preOrder->supplier_ordered_at?->toIso8601String(),
            'goods_arrived_at'         => $preOrder->goods_arrived_at?->toIso8601String(),
            'balance_requested_at'     => $preOrder->balance_requested_at?->toIso8601String(),
            'balance_due_at'           => $preOrder->balance_due_at?->toIso8601String(),
            'released_at'              => $preOrder->released_at?->toIso8601String(),
            'cancelled_at'             => $preOrder->cancelled_at?->toIso8601String(),
            'cancellation_reason'      => $preOrder->cancellation_reason,
            'pallet_estimate_amount'   => $preOrder->pallet_estimate_amount !== null ? (float) $preOrder->pallet_estimate_amount : null,
            'pallet_quote_amount'      => $preOrder->pallet_quote_amount !== null ? (float) $preOrder->pallet_quote_amount : null,
            'pallet_quote_over_estimate' => SendPreOrderUpdateEmail::make()->isPalletQuoteOverTolerance($preOrder),
            'can_pay_balance'          => $isOpen && (float) $order->total_amount > (float) $order->payment_amount,
            'terms'                    => $preOrder->terms,
        ];
    }
}
