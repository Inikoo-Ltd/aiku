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
        $organisation = $invoiceTransaction->organisation;

        if ($tradeUnits->isEmpty()) {
            return;
        }

        $values = [];
        foreach ($tradeUnits as $tradeUnit) {
            $orgStocks = $tradeUnit->orgStocks()->where('organisation_id', $organisation->id)->get();
            $total     = 0.0;
            foreach ($orgStocks as $orgStock) {
                $total += ($orgStock->pivot->quantity ?? 1) * (float) ($orgStock->unit_cost ?? 0);
            }
            $values[$tradeUnit->id] = $total;
        }

        $totalValue = array_sum($values);

        if ($totalValue <= 0) {
            return;
        }

        foreach ($tradeUnits as $tradeUnit) {
            if ($values[$tradeUnit->id] <= 0) {
                continue;
            }

            $factor = $values[$tradeUnit->id] / $totalValue;

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
