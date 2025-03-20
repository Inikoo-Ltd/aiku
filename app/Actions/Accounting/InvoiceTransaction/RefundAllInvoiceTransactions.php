<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-14h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;

class RefundAllInvoiceTransactions extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund): Invoice
    {
        $invoice = $refund->originalInvoice;

        foreach ($invoice->invoiceTransactions as $transaction) {
            $totalTrRefund = $transaction->transactionRefunds->sum('net_amount');
            $totalTr = $transaction->net_amount - abs($totalTrRefund);
            if ($totalTr <= 0) {
                continue;
            }
            CreateFullRefundInvoiceTransaction::make()->action($refund, $transaction);
        }

        return $refund;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, ActionRequest $request): void
    {
        $this->initialisationFromShop($refund->shop, $request);
        $this->handle($refund);
    }

    public function action(Invoice $refund): Invoice
    {
        $this->initialisationFromShop($refund->shop, []);
        return $this->handle($refund);
    }

}
