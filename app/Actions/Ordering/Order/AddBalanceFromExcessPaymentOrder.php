<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Ordering\Order;

class AddBalanceFromExcessPaymentOrder extends OrgAction
{
    use WithActionUpdate;
    public function handle(Order $order)
    {
        StoreCreditTransaction::make()->action($order->customer, [
            'amount' => $order->payment_amount - $order->total_amount,
            'notes' => 'Excess payment from order:'. $order->reference,
            'type' => CreditTransactionTypeEnum::FROM_EXCESS,
            'reason' => CreditTransactionReasonEnum::OTHER,
        ]);

        $order->refresh();
        $order = $this->update($order, [
            'payment_amount' => $order->total_amount
        ]);
    }

    public function asController(Order $order)
    {
        $this->initialisationFromShop($order->shop, []);
        $this->handle($order);
    }
}
