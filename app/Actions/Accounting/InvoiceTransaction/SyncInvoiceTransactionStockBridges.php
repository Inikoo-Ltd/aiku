<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasStock;
use App\Models\Inventory\OrgStock;
use App\Models\Goods\Stock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionStockBridges extends SyncInvoiceTransactionBridges implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(int $invoiceTransactionId): string
    {
        return (string)$invoiceTransactionId;
    }

    public function handle(int $invoiceTransactionId): void
    {
        $this->syncBridges($invoiceTransactionId);
    }

    protected function resolveBridgeTarget(OrgStock $orgStock): ?object
    {
        return $orgStock->stock;
    }

    protected function upsertBridge(InvoiceTransaction $invoiceTransaction, OrgStock $orgStock, object $bridgeTarget, float $factor): void
    {
        if (!$bridgeTarget instanceof Stock) {
            return;
        }

        InvoiceTransactionHasStock::updateOrCreate(
            [
                'invoice_transaction_id' => $invoiceTransaction->id,
                'stock_id'               => $bridgeTarget->id,
            ],
            [
                'stock_family_id' => $bridgeTarget->stock_family_id,
                'net_amount'      => $invoiceTransaction->net_amount * $factor,
                'org_net_amount'  => $invoiceTransaction->org_net_amount * $factor,
                'grp_net_amount'  => $invoiceTransaction->grp_net_amount * $factor,
            ]
        );
    }
}
