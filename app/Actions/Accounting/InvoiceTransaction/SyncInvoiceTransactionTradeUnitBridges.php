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

    public function getJobUniqueId(?int $invoiceTransactionId): string
    {
        return (string) $invoiceTransactionId ?? 'empty';
    }

    public function handle(?int $invoiceTransactionId): void
    {
        if (!$invoiceTransactionId) {
            return;
        }

        $invoiceTransaction = InvoiceTransaction::find($invoiceTransactionId);
        if (!$invoiceTransaction) {
            return;
        }

        if ($invoiceTransaction->model_type !== 'Product' || !$invoiceTransaction->model_id) {
            return;
        }

        $product = Product::find($invoiceTransaction->model_id);

        if (!$product) {
            return;
        }

        $tradeUnits   = $product->tradeUnits;
        $unitCount    = $tradeUnits->count();
        $organisation = $invoiceTransaction->organisation;

        if ($unitCount === 0) {
            return;
        }

        $values = [];
        foreach ($tradeUnits as $tradeUnit) {
            $values[$tradeUnit->id] = GetTradeUnitValue::run($tradeUnit, $organisation, $invoiceTransaction->date);
        }

        $totalValue = array_sum($values);

        foreach ($tradeUnits as $tradeUnit) {
            $factor = $totalValue > 0 ? $values[$tradeUnit->id] / $totalValue : 1 / $unitCount;

            InvoiceTransactionHasTradeUnit::updateOrCreate(
                [
                    'invoice_transaction_id' => $invoiceTransaction->id,
                    'trade_unit_id'          => $tradeUnit->id,
                ],
                [
                    'trade_unit_family_id' => $tradeUnit->trade_unit_family_id,
                    'net_amount'           => $invoiceTransaction->net_amount * $factor,
                    'org_net_amount'       => $invoiceTransaction->org_net_amount * $factor,
                    'grp_net_amount'       => $invoiceTransaction->grp_net_amount * $factor,
                ]
            );
        }
    }
}
