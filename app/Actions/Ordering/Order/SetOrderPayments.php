<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Mar 2025 21:56:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Illuminate\Support\Arr;

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
        $payStatus             = OrderPayStatusEnum::UNPAID;
        $payDetailedStatus     = OrderPayDetailedStatusEnum::UNPAID;
        /** @var Payment $payment */
        foreach (
            $order->payments()->where('payments.status', PaymentStatusEnum::SUCCESS)->get() as $payment
        ) {
            $runningPaymentsAmount += $payment->amount;
        }
        if ($runningPaymentsAmount >= $order->total_amount) {
            $payStatus = OrderPayStatusEnum::PAID;
        }


        if ($runningPaymentsAmount > $order->total_amount) {
            $payDetailedStatus = OrderPayDetailedStatusEnum::OVERPAID;
        } elseif ($runningPaymentsAmount == $order->total_amount) {
            $payDetailedStatus = OrderPayDetailedStatusEnum::PAID;
        } elseif ($runningPaymentsAmount > 0) {
            $payDetailedStatus = OrderPayDetailedStatusEnum::PARTIALLY_PAID;
        }


        $cutOffDate = Arr::get($order->shop->settings, 'unpaid_invoices_unknown_before', config('app.unpaid_invoices_unknown_before'));
        if ($cutOffDate) {
            $cutOffDate = Carbon::parse($cutOffDate);
        }

        if ($runningPaymentsAmount == 0 && $cutOffDate && $order->created_at->lt($cutOffDate)) {
            $payStatus         = OrderPayStatusEnum::UNKNOWN;
            $payDetailedStatus = OrderPayDetailedStatusEnum::UNKNOWN;
        }


        $order->update(
            [
                'payment_amount'      => $runningPaymentsAmount,
                'pay_detailed_status' => $payDetailedStatus,
                'pay_status'          => $payStatus,
            ]
        );


        return $order;
    }

}
