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

class ProcessInvoicePaidNotification extends OrgAction
{
    public string $jobQueue = 'ses';

    public function handle(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            return;
        }

        /**
         * Gated on the invoice, not on the account the payment came through: a cash on delivery
         * order settled via a bank or cash account is still cash on delivery, and the old
         * payment-account check silently swallowed the email in exactly those cases.
         */
        if (!$invoice->is_cash_on_delivery) {
            return;
        }

        if (!in_array($invoice->pay_detailed_status, [InvoicePayDetailedStatusEnum::PAID, InvoicePayDetailedStatusEnum::OVERPAID])) {
            return;
        }

        SendInvoicePaidEmailToCustomer::dispatch($invoice->customer, [
            'invoice_id' => $invoice->id,
        ]);
    }
}
