<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-14h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Laravel\Octane\Facades\Octane;
use Lorisleiva\Actions\ActionRequest;

class RefundAllInvoiceTransactions extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund): Invoice
    {
        /** @var Invoice $originalInvoice */
        $originalInvoice = $refund->originalInvoice;


        $totalTrRefund = $refund->invoiceTransactions()->sum('net_amount');
        $totalTr = $originalInvoice->invoiceTransactions()->sum('net_amount') - abs($totalTrRefund);
        if ($totalTr > 0) {
            $transactions = $originalInvoice->invoiceTransactions->where('net_amount', '>', 0);
            $tasks = [];
            foreach ($transactions->chunk(100) as $chunkedTransactions) {
                foreach ($chunkedTransactions as $transaction) {
                    $tasks[] = fn () => StoreRefundInvoiceTransaction::run($refund, $transaction, [
                        'net_amount' => $transaction->net_amount,
                        'refund_all' => true,
                    ]);
                }
                Octane::concurrently($tasks);
                $tasks = [];
            }

            CalculateInvoiceTotals::run($refund);
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

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund): Invoice
    {
        $this->initialisationFromShop($refund->shop, []);
        return $this->handle($refund);
    }

}
