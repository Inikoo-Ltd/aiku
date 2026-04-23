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
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionOrgStockBridges implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

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

        $orgStocks = $product->orgStocks;

        if ($orgStocks->isEmpty()) {
            return;
        }

        $weights = [];
        foreach ($orgStocks as $orgStock) {
            $weights[$orgStock->id] = (float) ($orgStock->sku_value ?? 0) * ($orgStock->pivot->quantity ?? 1);
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return;
        }

        foreach ($orgStocks as $orgStock) {
            if ($weights[$orgStock->id] <= 0) {
                continue;
            }

            $factor = $weights[$orgStock->id] / $totalWeight;

            InvoiceTransactionHasOrgStock::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'org_stock_id'           => $orgStock->id,
                ],
                [
                    'org_stock_family_id' => $orgStock->org_stock_family_id,
                    'net_amount'          => $invoiceTransaction->net_amount * $factor,
                    'org_net_amount'      => $invoiceTransaction->org_net_amount * $factor,
                    'grp_net_amount'      => $invoiceTransaction->grp_net_amount * $factor,
                ]
            );
        }
    }
}
