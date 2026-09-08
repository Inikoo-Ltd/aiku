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
use App\Models\Accounting\InvoiceTransaction;
use Laravel\Octane\Facades\Octane;
use Lorisleiva\Actions\ActionRequest;

class RefundAllTaxesInvoiceTransactions extends OrgAction
{
    /**
     * Refunds whatever tax of the original invoice has not been refunded yet.
     *
     * The tax still outstanding is measured against the invoice's own tax figure, not
     * against a fresh multiplication of its net, so a rounding penny cannot look like
     * tax left to refund. The lines show the tax one by one and are stored to the penny,
     * so the leftover between their sum and the outstanding figure goes on the last line.
     *
     * @throws \Throwable
     */
    public function handle(Invoice $refund): Invoice
    {
        /** @var Invoice $originalInvoice */
        $originalInvoice = $refund->originalInvoice;

        $totalTaxesRefund = $refund->invoiceTransactions()->sum('tax_amount');
        $totalTaxes = round($originalInvoice->tax_amount - abs($totalTaxesRefund), 2);


        if ($totalTaxes > 0) {
            $transactions = $originalInvoice->invoiceTransactions->where('net_amount', '>', 0)->values();
            $tasks = [];

            $taxAmounts = $transactions->map(fn (InvoiceTransaction $transaction) => round($transaction->net_amount * $transaction->taxCategory->rate, 2));

            if ($taxAmounts->isNotEmpty()) {
                $lastLine              = $taxAmounts->count() - 1;
                $taxAmounts[$lastLine] = round($taxAmounts[$lastLine] + $totalTaxes - $taxAmounts->sum(), 2);
            }

            foreach ($transactions->chunk(100) as $chunkedTransactions) {
                foreach ($chunkedTransactions as $index => $transaction) {
                    $taxAmount = $taxAmounts[$index];
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
