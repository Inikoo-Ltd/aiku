<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Order;

class RollbackOrderAfterDeliveryNoteCancellation extends OrgAction
{
    public function handle(Order $order): void
    {
        $order = UpdateOrder::make()->action($order, [
             'state' => OrderStateEnum::SUBMITTED,
             'in_warehouse_at' => null,
             'handling_at' => null,
             'packed_at' => null,
             'finalised_at' => null,
             'settled_at' => null,
             'dispatched_at' => null
         ], 0, false);

        $order->refresh();

        foreach ($order->transactions as $transaction) {
            UpdateTransaction::make()->action($transaction, [
                'state' => TransactionStateEnum::SUBMITTED,
                'in_warehouse_at' => null,
                'quantity_dispatched' => 0,
                'quantity_picked' => 0,
            ], false);
        }
    }

    public function action(Order $order): void
    {
        $this->initialisationFromShop($order->shop, []);

        $this->handle($order);
    }
}
