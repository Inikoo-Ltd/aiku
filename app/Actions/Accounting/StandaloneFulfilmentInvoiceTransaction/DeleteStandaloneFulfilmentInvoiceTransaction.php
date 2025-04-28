<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-09h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Accounting\InvoiceTransaction\DeleteInProcessInvoiceTransaction;
use App\Actions\OrgAction;
use App\Models\Accounting\InvoiceTransaction;
use Lorisleiva\Actions\ActionRequest;

class DeleteStandaloneFulfilmentInvoiceTransaction extends OrgAction
{
    public function handle(InvoiceTransaction $invoiceTransaction): void
    {
        $invoice = $invoiceTransaction->invoice;


        DeleteInProcessInvoiceTransaction::make()->action($invoiceTransaction);
        $invoice->refresh();
        CalculateInvoiceTotals::run($invoice);

    }


    public function asController(InvoiceTransaction $invoiceTransaction, ActionRequest $request): void
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $request);

        $this->handle($invoiceTransaction);
    }

    public function action(InvoiceTransaction $invoiceTransaction): void
    {
        $this->initialisationFromShop($invoiceTransaction->shop, []);

        $this->handle($invoiceTransaction);
    }


}
