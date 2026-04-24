<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasOrgStock;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionOrgStockBridges extends SyncInvoiceTransactionBridges implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales_slave';

    public function getJobUniqueId(int $invoiceTransactionId): string
    {
        return (string) $invoiceTransactionId;
    }

    public function handle(int $invoiceTransactionId): void
    {
        $this->syncBridges($invoiceTransactionId);
    }

    protected function resolveBridgeTarget(OrgStock $orgStock): ?object
    {
        return $orgStock;
    }

    protected function upsertBridge(InvoiceTransaction $invoiceTransaction, OrgStock $orgStock, object $bridgeTarget, float $factor): void
    {
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
