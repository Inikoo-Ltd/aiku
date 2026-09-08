<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AddBalanceFromExcessPaymentOrder extends OrgAction
{
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        $refunds = Invoice::where('order_id', $order->id)->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->get();

        $totalAmount = $order->total_amount + $refunds->sum('total_amount');

        $amount = round($order->payment_amount - $totalAmount, 2);

        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $order->shop->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first();

        foreach ($this->allocate($amount, $refunds) as [$refund, $allocatedAmount]) {
            $refundPayment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, [
                'amount'              => -$allocatedAmount,
                'reference'           => 'ref-bal-'.Str::ulid(),
                'status'              => PaymentStatusEnum::SUCCESS->value,
                'state'               => PaymentStateEnum::COMPLETED->value,
                'type'                => PaymentTypeEnum::REFUND,
            ]);

            StoreCreditTransaction::make()->action($order->customer, [
                'payment_id' => $refundPayment->id,
                'amount' => $allocatedAmount,
                'notes'  => 'Excess payment from order:'.$order->reference,
                'type'   => CreditTransactionTypeEnum::FROM_EXCESS,
                'reason' => CreditTransactionReasonEnum::OTHER,
            ]);

            AttachPaymentToOrder::make()->action($order, $refundPayment, []);

            if ($refund) {
                AttachPaymentToInvoice::make()->action($refund, $refundPayment, []);
            }
        }

        $order->refresh();


        request()->session()->flash('modal', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Excess payment has been returned as balance.'),
        ]);
    }

    /**
     * @return array<int, array{0: ?Invoice, 1: float}>
     */
    private function allocate(float $amount, Collection $refunds): array
    {
        $allocations   = [];
        $pendingAmount = $amount;

        /** @var Invoice $refund */
        foreach ($refunds as $refund) {
            $owedToCustomer = round(abs($refund->total_amount) - abs($refund->payment_amount), 2);
            if ($owedToCustomer <= 0 || $pendingAmount <= 0) {
                continue;
            }

            $allocatedAmount = min($pendingAmount, $owedToCustomer);
            $allocations[]   = [$refund, $allocatedAmount];
            $pendingAmount   = round($pendingAmount - $allocatedAmount, 2);
        }

        if ($pendingAmount > 0) {
            $allocations[] = [null, $pendingAmount];
        }

        return $allocations;
    }

    public function asController(Order $order): void
    {
        $this->initialisationFromShop($order->shop, []);
        $this->handle($order);
    }
}
