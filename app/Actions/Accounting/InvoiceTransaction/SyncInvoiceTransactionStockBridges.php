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

        $quantity = abs($invoiceTransaction->quantity ?? 0);

        foreach ($product->orgStocks as $orgStock) {
            $stock = $orgStock->stock;

            if (! $stock) {
                continue;
            }

            $netAmount = ($orgStock->unit_commercial_value ?? 0) * $quantity;
            $orgNetAmount = $invoiceTransaction->org_exchange ? $netAmount * $invoiceTransaction->org_exchange : 0;
            $grpNetAmount = $invoiceTransaction->grp_exchange ? $netAmount * $invoiceTransaction->grp_exchange : 0;

            InvoiceTransactionHasStock::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'stock_id' => $stock->id,
                ],
                [
                    'stock_family_id' => $stock->stock_family_id,
                    'net_amount' => $netAmount,
                    'org_net_amount' => $orgNetAmount,
                    'grp_net_amount' => $grpNetAmount,
                ]
            );
        }
    }
}
