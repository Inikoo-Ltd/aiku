<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-14h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use Lorisleiva\Actions\ActionRequest;

class CreateFullRefundInvoiceTransaction extends OrgAction
{
    public function handle(Invoice $refund, InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        if ($refund->is_tax_only) {
            if ($invoiceTransaction->tax_amount > 0) {
                $invoiceTransaction = StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
                    'tax_amount' => ($invoiceTransaction->net_amount * $refund->taxCategory->rate)
                ]);
            }
        } else {
            if ($invoiceTransaction->net_amount > 0) {
                $invoiceTransaction = StoreRefundInvoiceTransaction::make()->action($refund, $invoiceTransaction, [
                    'net_amount' => $invoiceTransaction->net_amount
                ]);
            }
        }


        return $invoiceTransaction;
    }


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
