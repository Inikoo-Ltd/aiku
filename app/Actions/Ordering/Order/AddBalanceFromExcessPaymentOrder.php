<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Str;

class AddBalanceFromExcessPaymentOrder extends OrgAction
{
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $totalAmount = $order->total_amount;
        /** @var Invoice $refund */
        foreach (Invoice::where('order_id', $order->id)->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->get() as $refund) {
            $totalAmount += $refund->total_amount;
        }

        $amount = round($order->payment_amount - $totalAmount, 2);

        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first();

        $refundPayment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, [
            'amount'              => -$amount,
            'reference'           => 'ref-bal-'.Str::ulid(),
            'status'              => PaymentStatusEnum::SUCCESS->value,
            'state'               => PaymentStateEnum::COMPLETED->value,
            'type'                => PaymentTypeEnum::REFUND,
        ]);

        StoreCreditTransaction::make()->action($order->customer, [
            'payment_id' => $refundPayment->id,
            'amount' => $amount,
            'notes'  => 'Excess payment from order:'.$order->reference,
            'type'   => CreditTransactionTypeEnum::FROM_EXCESS,
            'reason' => CreditTransactionReasonEnum::OTHER,
        ]);


        AttachPaymentToOrder::make()->action($order, $refundPayment, []);

        $order->refresh();


        request()->session()->flash('modal', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Excess payment has been returned as balance.'),
        ]);
    }

    public function asController(Order $order): void
    {
        $this->initialisationFromShop($order->shop, []);
        $this->handle($order);
    }
}
