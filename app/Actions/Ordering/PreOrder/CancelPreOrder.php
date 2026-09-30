<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Enums\Ordering\Order\OrderCancellationReasonEnum;
use App\Enums\Ordering\PreOrder\PreOrderCancellationReasonEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The cancellation terms the customer accepted at checkout (HELP-3432):
 * - our failure to deliver (late, supplier cannot supply, supplier minimum not met, pallet quote
 *   over the estimate) refunds everything, deposit included, trade or dropshipping;
 * - a trade customer gets everything back until we order from the supplier, after that the
 *   made-to-order deposit is kept; back-orders are refunded in full any time before dispatch;
 * - a balance not paid in time cancels the order and keeps the deposit;
 * - dropshipping pre-orders are not refundable once placed.
 * The goods kept for it go back on sale.
 */
class CancelPreOrder
{
    use AsObject;

    /**
     * @throws \Throwable
     */
    public function handle(PreOrder $preOrder, PreOrderCancellationReasonEnum $reason, ?string $notes = null): PreOrder
    {
        return DB::transaction(function () use ($preOrder, $reason, $notes) {
            $preOrder->lockInState(PreOrderStateEnum::open());

            $order           = $preOrder->order->refresh();
            $refundAmount    = $this->refundAmount($preOrder, $reason);
            $keptAmount      = round(max(0, (float) $order->payment_amount - $refundAmount), 2);
            $wasHoldingStock = in_array($preOrder->state, PreOrderStateEnum::holdingStock());

            $preOrder->update([
                'state'               => PreOrderStateEnum::CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason->value,
            ]);

            CancelOrder::make()->action($order, [
                'cancellation_reason' => $reason == PreOrderCancellationReasonEnum::CUSTOMER_REQUEST
                    ? OrderCancellationReasonEnum::CUSTOMER_REQUEST->value
                    : OrderCancellationReasonEnum::OTHER->value,
                'cancellation_notes'  => trim(implode(' ', array_filter([
                    $reason->label().'.',
                    $keptAmount > 0 ? __('Deposit kept: :amount.', ['amount' => $keptAmount]) : null,
                    $notes,
                ]))),
                'refund_amount'       => $refundAmount,
            ]);

            if ($wasHoldingStock) {
                HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));
            }

            SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::CANCELLED, [
                'reason'        => $reason->label(),
                'refund_amount' => $refundAmount,
            ])->afterCommit();

            return $preOrder;
        });
    }

    /**
     * A balance not paid in time keeps at most the made-to-order deposit, and dropshipping has
     * none: the rest of what was paid, pennies left short by the split included, goes back.
     */
    public function refundAmount(PreOrder $preOrder, PreOrderCancellationReasonEnum $reason): float
    {
        $paid    = max(0, (float) $preOrder->order->payment_amount);
        $deposit = $preOrder->is_trade ? (float) Arr::get($preOrder->data, 'made_to_order_deposit_amount', 0) : 0;

        if ($reason->isFullRefund()) {
            return $paid;
        }

        if ($reason == PreOrderCancellationReasonEnum::BALANCE_NOT_PAID) {
            return round(max(0, $paid - $deposit), 2);
        }

        if (!$preOrder->is_trade) {
            return 0;
        }

        if ($reason == PreOrderCancellationReasonEnum::CUSTOMER_REQUEST && $preOrder->isWithinFreeCancellation()) {
            return $paid;
        }

        return round(max(0, $paid - $deposit), 2);
    }
}
