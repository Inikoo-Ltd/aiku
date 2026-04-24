<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Apr 2026 23:49:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;

abstract class SyncInvoiceTransactionBridges
{
    protected function syncBridges(int $invoiceTransactionId): void
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
            $bridgeTarget = $this->resolveBridgeTarget($orgStock);
            if (!$bridgeTarget) {
                continue;
            }

            $weights[$bridgeTarget->id] = (float) ($orgStock->sku_value ?? 0) * ($orgStock->pivot->quantity ?? 1);
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return;
        }

        foreach ($orgStocks as $orgStock) {
            $bridgeTarget = $this->resolveBridgeTarget($orgStock);
            if (!$bridgeTarget) {
                continue;
            }

            if ($weights[$bridgeTarget->id] <= 0) {
                continue;
            }

            $factor = $weights[$bridgeTarget->id] / $totalWeight;

            $this->upsertBridge($invoiceTransaction, $orgStock, $bridgeTarget, $factor);
        }
    }

    abstract protected function resolveBridgeTarget(OrgStock $orgStock): ?object;

    abstract protected function upsertBridge(InvoiceTransaction $invoiceTransaction, OrgStock $orgStock, object $bridgeTarget, float $factor): void;
}
