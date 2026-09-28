<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Actions\Ordering\Order\UpdateOrderPaymentsStatus;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The goods are here: the customer is emailed a payment link for what is still owed and the
 * goods are kept for them until it is paid, or until the order is cancelled for not paying.
 */
class RequestPreOrderBalance
{
    use AsObject;

    public function handle(PreOrder $preOrder): PreOrder
    {
        $order = UpdateOrderPaymentsStatus::run($preOrder->order);

        if ($order->pay_status == OrderPayStatusEnum::PAID) {
            return ReleasePreOrder::run($preOrder);
        }

        $preOrder->update([
            'state'                => PreOrderStateEnum::BALANCE_REQUESTED,
            'balance_requested_at' => now(),
            'balance_due_at'       => now()->addDays((int) $preOrder->shop->preOrderSetting('balance_due_days')),
        ]);

        HydratePreOrderReservedStock::run(HydratePreOrderReservedStock::make()->orgStockIds($preOrder));

        SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::BALANCE_REQUEST)->afterCommit();

        return $preOrder;
    }
}
