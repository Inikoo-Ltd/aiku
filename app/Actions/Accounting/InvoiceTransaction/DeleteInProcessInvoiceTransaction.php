<?php

/*
 * author Arya Permana - Kirin
 * created on 04-02-2025-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice;
use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\OrgAction;
use App\Models\Accounting\InvoiceTransaction;
use App\Actions\Traits\Authorisations\WithRefundEditAuthorisation;

class DeleteInProcessInvoiceTransaction extends OrgAction
{
    use WithDeleteRefundInProcessTransaction;
    use WithRefundEditAuthorisation;

    public function handle(InvoiceTransaction $invoiceTransaction): void
    {
        EnsureInvoiceNotSentAsEInvoice::run($invoiceTransaction->invoice);

        $invoiceTransaction->delete();
        CalculateInvoiceTotals::run($invoiceTransaction->invoice);
    }



}
