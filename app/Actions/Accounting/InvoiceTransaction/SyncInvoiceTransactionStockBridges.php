<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasStock;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionStockBridges implements ShouldBeUnique, ShouldQueue
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $invoiceTransactionId): string
    {
        return (string) $invoiceTransactionId;
    }

    public function handle(int $invoiceTransactionId): void
    {
        $invoiceTransaction = InvoiceTransaction::find($invoiceTransactionId);

        if (! $invoiceTransaction || $invoiceTransaction->model_type !== 'Product' || ! $invoiceTransaction->model_id) {
            return;
        }

        $product = Product::find($invoiceTransaction->model_id);

        if (! $product) {
            return;
        }

        $orgStocks = $product->orgStocks;

        if ($orgStocks->isEmpty()) {
            return;
        }

        $weights = [];
        foreach ($orgStocks as $orgStock) {
            $stock = $orgStock->stock;
            if (! $stock) {
                continue;
            }
            $weights[$stock->id] = (float) ($orgStock->unit_cost ?? 0) * ($orgStock->pivot->quantity ?? 1);
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return;
        }

        foreach ($orgStocks as $orgStock) {
            $stock = $orgStock->stock;
            if (! $stock) {
                continue;
            }

            if ($weights[$stock->id] <= 0) {
                continue;
            }

            $factor = $weights[$stock->id] / $totalWeight;

            InvoiceTransactionHasStock::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'stock_id'               => $stock->id,
                ],
                [
                    'stock_family_id' => $stock->stock_family_id,
                    'net_amount'      => $invoiceTransaction->net_amount * $factor,
                    'org_net_amount'  => $invoiceTransaction->org_net_amount * $factor,
                    'grp_net_amount'  => $invoiceTransaction->grp_net_amount * $factor,
                ]
            );
        }
    }
}
