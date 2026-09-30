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
use Illuminate\Validation\ValidationException;
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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(PreOrder $preOrder, PreOrderCancellationReasonEnum $reason, ?string $notes = null): PreOrder
    {
        if (!in_array($preOrder->state, PreOrderStateEnum::open())) {
            throw ValidationException::withMessages([
                'pre_order' => __('This pre-order can no longer be cancelled.'),
            ]);
        }

        $refundAmount    = $this->refundAmount($preOrder, $reason);
        $wasHoldingStock = in_array($preOrder->state, PreOrderStateEnum::holdingStock());

        $preOrder->update([
            'state'               => PreOrderStateEnum::CANCELLED,
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason->value,
        ]);

        CancelOrder::make()->action($preOrder->order, [
            'cancellation_reason' => $reason == PreOrderCancellationReasonEnum::CUSTOMER_REQUEST
                ? OrderCancellationReasonEnum::CUSTOMER_REQUEST->value
                : OrderCancellationReasonEnum::OTHER->value,
            'cancellation_notes'  => trim($reason->label().'. '.$notes),
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
    }

    public function refundAmount(PreOrder $preOrder, PreOrderCancellationReasonEnum $reason): float
    {
        $paid = max(0, (float) $preOrder->order->payment_amount);

        if ($reason->isFullRefund()) {
            return $paid;
        }

        if (!$preOrder->is_trade) {
            return 0;
        }

        if ($reason == PreOrderCancellationReasonEnum::CUSTOMER_REQUEST && $preOrder->isWithinFreeCancellation()) {
            return $paid;
        }

        return round(max(0, $paid - (float) Arr::get($preOrder->data, 'made_to_order_deposit_amount', 0)), 2);
    }

    /**
     * What a customer cancelling now is entitled to: being late, or a pallet quote over the
     * estimate, is our failure.
     */
    public function customerReason(PreOrder $preOrder): PreOrderCancellationReasonEnum
    {
        if ($preOrder->isLate()) {
            return PreOrderCancellationReasonEnum::LATE;
        }

        if ($preOrder->is_trade && SendPreOrderUpdateEmail::make()->isPalletQuoteOverTolerance($preOrder)) {
            return PreOrderCancellationReasonEnum::PALLET_QUOTE_OVER_ESTIMATE;
        }

        return PreOrderCancellationReasonEnum::CUSTOMER_REQUEST;
    }
}
