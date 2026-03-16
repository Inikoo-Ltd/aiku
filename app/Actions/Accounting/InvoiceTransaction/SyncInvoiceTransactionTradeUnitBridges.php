<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasTradeUnit;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncInvoiceTransactionTradeUnitBridges implements ShouldQueue, ShouldBeUnique
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

        $quantity = abs($invoiceTransaction->quantity ?? 0);

        foreach ($product->tradeUnits as $tradeUnit) {
            // Todo: also we need unit_commercial_value in trade_units or at least fix the cost_price (all cost_price are null)
            $netAmount    = ($tradeUnit->cost_price ?? 0) * $quantity;
            $orgNetAmount = $invoiceTransaction->org_exchange ? $netAmount * $invoiceTransaction->org_exchange : 0;
            $grpNetAmount = $invoiceTransaction->grp_exchange ? $netAmount * $invoiceTransaction->grp_exchange : 0;

            InvoiceTransactionHasTradeUnit::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'trade_unit_id'          => $tradeUnit->id,
                ],
                [
                    'trade_unit_family_id' => $tradeUnit->trade_unit_family_id,
                    'net_amount'           => $netAmount,
                    'org_net_amount'       => $orgNetAmount,
                    'grp_net_amount'       => $grpNetAmount,
                ]
            );
        }
    }
}
