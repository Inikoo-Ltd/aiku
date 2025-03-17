<?php
/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-14h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use Lorisleiva\Actions\ActionRequest;

class CreateFullRefundInvoiceTransaction extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund, InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        $invoiceTransaction = StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
            'gross_amount' => $invoiceTransaction->gross_amount
        ]);

        return $invoiceTransaction;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, InvoiceTransaction $invoiceTransaction, ActionRequest $request): void
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $request);
        $this->handle($refund, $invoiceTransaction);
    }

    public function action(Invoice $refund, InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        $this->initialisationFromShop($invoiceTransaction->shop, []);
        return $this->handle($refund, $invoiceTransaction);
    }

}
