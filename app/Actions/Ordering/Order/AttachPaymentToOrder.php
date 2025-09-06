<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

class AttachPaymentToOrder extends OrgAction
{
    public function handle(Order $order, Payment $payment, array $modelData): void
    {

        $amount = Arr::get($modelData, 'amount', $payment->amount);

        $order->payments()->attach($payment, [
            'amount' => $amount,
        ]);

        UpdateOrderPaymentsStatus::run($order);

    }

    public function rules(): array
    {
        return [
            'amount'    => ['sometimes', 'numeric'],
        ];
    }


    public function action(Order $order, Payment $payment, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, $modelData);
        $this->handle($order, $payment, $modelData);
    }
}
