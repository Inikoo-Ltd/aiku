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
        return (string) $invoiceTransactionId??'empty';
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

        $tradeUnits = $product->tradeUnits;
        $unitCount  = $tradeUnits->count();

        if ($unitCount === 0) {
            return;
        }

        //todo use  $value= $tradeUnit->getCommercialValue()
//        $totalCommercialValue=0;
//        foreach ($tradeUnits as $tradeUnit) {
//            $value= $tradeUnit->getValue($invoiceTransaction->organisation);
//            $totalCommercialValue += $value;
//        }

        $netAmount    = $invoiceTransaction->net_amount / $unitCount;
        $orgNetAmount = $invoiceTransaction->org_net_amount / $unitCount;
        $grpNetAmount = $invoiceTransaction->grp_net_amount / $unitCount;

        foreach ($tradeUnits as $tradeUnit) {

//            $factor=$tradeUnit->getValue()/$totalCommercialValue;
//            $netAmount    = $invoiceTransaction->net_amount * $factor;
//            $orgNetAmount = $invoiceTransaction->org_net_amount * $factor;
//            $grpNetAmount = $invoiceTransaction->grp_net_amount * $factor;

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
