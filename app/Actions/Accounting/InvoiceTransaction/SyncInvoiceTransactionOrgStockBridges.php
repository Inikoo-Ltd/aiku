<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasOrgStock;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionOrgStockBridges implements ShouldQueue, ShouldBeUnique
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

        if (!$invoiceTransaction || $invoiceTransaction->model_type !== 'Product' || !$invoiceTransaction->model_id) {
            return;
        }

        $product = Product::find($invoiceTransaction->model_id);

        if (!$product) {
            return;
        }

        $orgStocks   = $product->orgStocks;
        $stockCount  = $orgStocks->count();

        if ($stockCount === 0) {
            return;
        }

        $totalWeight = $orgStocks->sum(fn ($os) => ($os->unit_cost ?? 0) * ($os->pivot->quantity ?? 1));

        foreach ($orgStocks as $orgStock) {
            $weight = ($orgStock->unit_cost ?? 0) * ($orgStock->pivot->quantity ?? 1);
            $share  = $totalWeight > 0 ? $weight / $totalWeight : 1 / $stockCount;

            InvoiceTransactionHasOrgStock::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'org_stock_id'           => $orgStock->id,
                ],
                [
                    'org_stock_family_id' => $orgStock->org_stock_family_id,
                    'net_amount'          => $invoiceTransaction->net_amount * $share,
                    'org_net_amount'      => $invoiceTransaction->org_net_amount * $share,
                    'grp_net_amount'      => $invoiceTransaction->grp_net_amount * $share,
                ]
            );
        }
    }
}
