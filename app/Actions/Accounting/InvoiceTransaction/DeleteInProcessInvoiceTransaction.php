<?php

/*
 * author Arya Permana - Kirin
 * created on 04-02-2025-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Models\Accounting\InvoiceTransaction;

class DeleteInProcessInvoiceTransaction extends OrgAction
{
    use WithDeleteRefundInProcessTransaction;

    public function handle(InvoiceTransaction $invoiceTransaction): void
    {
        $invoiceTransaction->delete();
    }



}
