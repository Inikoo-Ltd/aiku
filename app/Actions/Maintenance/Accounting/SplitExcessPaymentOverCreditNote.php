<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\CreditTransaction\UpdateCreditTransaction;
use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Payment\UpdatePayment;
use App\Actions\Accounting\PaymentAccountShop\Hydrators\PaymentAccountShopHydratePayments;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateCreditTransactions;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SplitExcessPaymentOverCreditNote
{
    use AsAction;

    public string $commandSignature = 'repair:split_excess_payment {payment} {--apply}';

    public function handle(Payment $payment, Invoice $refund, float $refundedAmount): Payment
    {
        return DB::transaction(function () use ($payment, $refund, $refundedAmount) {
            $excessAmount = round(abs($payment->amount) - $refundedAmount, 2);
            $ratio        = $excessAmount / abs($payment->amount);

            UpdatePayment::make()->action($payment, [
                'amount'     => -$excessAmount,
                'org_amount' => round($payment->org_amount * $ratio, 2),
                'grp_amount' => round($payment->grp_amount * $ratio, 2),
            ]);

            $creditTransactions = CreditTransaction::where('payment_id', $payment->id)->get();
            foreach ($creditTransactions as $creditTransaction) {
                UpdateCreditTransaction::make()->action($creditTransaction, ['amount' => $excessAmount]);
            }

            $refundPayment = StorePayment::make()->action($payment->customer, $payment->paymentAccount, [
                'amount'    => -$refundedAmount,
                'reference' => 'ref-bal-'.Str::ulid(),
                'date'      => $payment->date,
                'status'    => PaymentStatusEnum::SUCCESS->value,
                'state'     => PaymentStateEnum::COMPLETED->value,
                'type'      => PaymentTypeEnum::REFUND,
            ]);

            StoreCreditTransaction::make()->action($payment->customer, [
                'payment_id' => $refundPayment->id,
                'amount'     => $refundedAmount,
                'date'       => $payment->date,
                'notes'      => $creditTransactions->first()?->notes,
                'type'       => CreditTransactionTypeEnum::FROM_EXCESS,
            ]);

            foreach ($payment->orders as $order) {
                AttachPaymentToOrder::make()->action($order, $refundPayment, []);
            }
            AttachPaymentToInvoice::make()->action($refund, $refundPayment, []);

            CustomerHydrateCreditTransactions::run($payment->customer_id);
            if ($payment->paymentAccountShop) {
                PaymentAccountShopHydratePayments::run($payment->paymentAccountShop);
            }

            return $refundPayment;
        });
    }

    public function asCommand(Command $command): int
    {
        $payment = Payment::find($command->argument('payment'));

        if (!$payment || $payment->invoices()->exists()) {
            $command->error('Payment not found, or already attached to an invoice.');

            return 1;
        }

        $order  = $payment->orders->first();
        $refunds = Invoice::where('order_id', $order?->id)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->where('in_process', false)
            ->where('pay_status', InvoicePayStatusEnum::UNPAID)
            ->get();

        if ($refunds->count() !== 1) {
            $command->error('Expected exactly one unpaid credit note on the order, found '.$refunds->count().'.');

            return 1;
        }

        $refund         = $refunds->first();
        $refundedAmount = round(abs($refund->total_amount) - abs($refund->payment_amount), 2);

        if ($refundedAmount >= round(abs($payment->amount), 2)) {
            $command->error('The payment does not exceed what the credit note owes, repair:excess_payment_refunds_not_attached_to_invoice covers it.');

            return 1;
        }

        $command->line('Payment '.$payment->id.' of '.$payment->amount.' on order '.$order->reference.' becomes:');
        $command->line('  '.$refund->reference.' paid with '.-$refundedAmount);
        $command->line('  excess kept on the order: '.-round(abs($payment->amount) - $refundedAmount, 2));

        if (!$command->option('apply')) {
            $command->info('Dry run, nothing written. Pass --apply to write.');

            return 0;
        }

        $refundPayment = $this->handle($payment, $refund, $refundedAmount);
        $command->info('Done, credit note paid by payment '.$refundPayment->id.'.');

        return 0;
    }
}
