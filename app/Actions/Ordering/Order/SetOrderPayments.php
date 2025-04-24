<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Mar 2025 21:56:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;

class SetOrderPayments extends OrgAction
{
    use WithHydrateCommand;

    public string $commandSignature = 'orders:set_payments {organisations?*} {--S|shop= shop slug} {--s|slug=}';


    public function __construct()
    {
        $this->model = Order::class;
    }

    protected function handle(Order $order): Order
    {
        $runningPaymentsAmount = 0;
        $payStatus = OrderPayStatusEnum::UNPAID;
        /** @var Payment $payment */
        foreach (
            $order->payments()->where('payments.status', PaymentStatusEnum::SUCCESS)->get() as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;
            if ($payStatus == OrderPayStatusEnum::UNPAID && $runningPaymentsAmount >= $order->total_amount) {
                $payStatus = OrderPayStatusEnum::PAID;
            }
        }

        $order->update(
            [
                'payment_amount' => $runningPaymentsAmount
            ]
        );

        return $order;
    }

}
