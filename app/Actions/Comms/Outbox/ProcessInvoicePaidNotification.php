<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: 06-07-2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoicePayDetailedStatusEnum;
use App\Models\Accounting\Invoice;
use App\Actions\Comms\Email\SendInvoicePaidEmailToCustomer;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;

class ProcessInvoicePaidNotification extends OrgAction
{
    public string $jobQueue = 'ses';

    public function handle(int $invoiceId, int $paymentId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $payment = $invoice->payments()->find($paymentId);

        if (!$payment) {
            return;
        }
        //   // TODO: This should be moved to a job, and the email should be sent only if the outbox is active and applicable
        if ($invoice && in_array($invoice->pay_detailed_status, [InvoicePayDetailedStatusEnum::PAID, InvoicePayDetailedStatusEnum::OVERPAID])
            && $payment->paymentAccount->type == PaymentAccountTypeEnum::CASH_ON_DELIVERY) {

            \Log::info('Processing invoice paid notification', ['invoice_id' => $invoice->id, 'payment_id' => $payment->id]);

            // SendInvoicePaidEmailToCustomer::dispatch($invoice->order->customer, [
            //     'order_id'   => $invoice->order->id,
            //     'amount'     => $invoice->amount,
            //     'invoice_id' => $invoice->id,
            // ]);
        }

    }
}
