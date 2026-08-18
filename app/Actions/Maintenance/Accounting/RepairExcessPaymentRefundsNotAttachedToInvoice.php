<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\AttachPaymentToInvoice;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairExcessPaymentRefundsNotAttachedToInvoice
{
    use AsAction;

    public string $commandSignature = 'repair:excess_payment_refunds_not_attached_to_invoice {--apply}';

    public function handle(Payment $payment, Invoice $refund): void
    {
        AttachPaymentToInvoice::make()->action($refund, $payment, []);
    }

    public function asCommand(Command $command): int
    {
        $apply    = $command->option('apply');
        $repaired = 0;
        $skipped  = [];

        $payments = Payment::where('reference', 'like', 'ref-bal-%')
            ->where('status', PaymentStatusEnum::SUCCESS)
            ->whereDoesntHave('invoices')
            ->whereHas('orders')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            foreach ($payment->orders as $order) {
                $refunds = Invoice::where('order_id', $order->id)
                    ->where('type', InvoiceTypeEnum::REFUND)
                    ->where('in_process', false)
                    ->where('pay_status', InvoicePayStatusEnum::UNPAID)
                    ->get();

                if ($refunds->isEmpty()) {
                    continue;
                }

                $owed = fn (Invoice $refund) => round(abs($refund->total_amount) - abs($refund->payment_amount), 2);
                $paid = round(abs($payment->amount), 2);

                $refund = $refunds->first(fn (Invoice $refund) => abs($owed($refund) - $paid) <= 0.05)
                    ?? ($refunds->count() === 1 ? $refunds->first() : null);

                if (!$refund || $paid > $owed($refund) + 0.05) {
                    $skipped[] = [$payment->id, $order->reference, (float) $payment->amount, $refunds->count(), $refunds->sum(fn (Invoice $refund) => $owed($refund))];
                    continue;
                }

                $command->line(($apply ? 'repairing ' : 'would repair ').$order->reference.' '.$refund->reference.' '.$payment->amount);

                if ($apply) {
                    $this->handle($payment, $refund);
                }
                $repaired++;
            }
        }

        if ($skipped) {
            $command->warn('Needs a human, left untouched:');
            $command->table(['payment', 'order', 'amount', 'credit notes', 'owed'], $skipped);
        }

        $command->info(($apply ? 'Repaired ' : 'Would repair ').$repaired.' payment(s), skipped '.count($skipped).'.');

        return 0;
    }
}
