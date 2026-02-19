<?php

/*
 * author Louis Perez
 * created on 06-02-2026-09h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Laravel\Octane\Facades\Octane;
use Lorisleiva\Actions\ActionRequest;

class RefundAllTaxesInvoiceTransactions extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund): Invoice
    {
        /** @var Invoice $originalInvoice */
        $originalInvoice = $refund->originalInvoice;

        $taxRate = $originalInvoice->taxCategory->rate;

        $totalTaxesRefund = $refund->invoiceTransactions()->sum('tax_amount');
        $totalTaxes = ($originalInvoice->invoiceTransactions()->sum('net_amount') * $taxRate) - abs($totalTaxesRefund);


        if ($totalTaxes > 0) {
            $transactions = $originalInvoice->invoiceTransactions->where('net_amount', '>', 0);
            $tasks = [];
            foreach ($transactions->chunk(100) as $chunkedTransactions) {
                foreach ($chunkedTransactions as $transaction) {
                    $taxAmount =  ($transaction->net_amount * $taxRate);
                    $tasks[] = fn () => StoreRefundInvoiceTransaction::run($refund, $transaction, [
                        'tax_amount' => $taxAmount,
                        'amount_total' => $taxAmount,
                        'is_tax_only' => true,
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
