<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RepairOrderAmountsAfterMigration
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order, Command $command): void
    {
        $updateInvoice = false;

        foreach ($order->transactions as $transaction) {
            if ($transaction->model_type != 'Product') {
                continue;
            }

            $oldHistoricAssetId = $transaction->historic_asset_id;

            /** @var \App\Models\Catalogue\Product $product */
            $product            = $transaction->model;
            $newHistoricAssetId = $product->current_historic_asset_id;


            $grossAmountFromHistoric = $transaction->historicAsset->price * $transaction->quantity_ordered;
            if ($grossAmountFromHistoric == $transaction->gross_amount) {
                $command->line('all ok');
                continue;
            }


            if ($oldHistoricAssetId != $newHistoricAssetId) {
                $currentGross = $transaction->gross_amount;

                $newGrossAmount = $product->currentHistoricProduct->price * $transaction->quantity_ordered;

                if ($currentGross != $newGrossAmount) {
                    $command->error("Product: $product->slug - old net amount: $currentGross - new net amount: $newGrossAmount");
                } else {
                    $command->info("Product: $product->slug - old historic asset id: $oldHistoricAssetId - new historic asset id: $newHistoricAssetId");
                    $transaction->update([
                        'historic_asset_id' => $newHistoricAssetId,
                    ]);
                    $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();


                    if ($invoiceTransaction) {
                        $updateInvoice = true;
                        $dataToUpdate  = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction);

                        $invoiceTransaction->update($dataToUpdate);
                    }
                }
            }
        }
        $invoice = $order->invoices()->first();
        if ($invoice && $updateInvoice) {
            CalculateInvoiceTotals::run($invoice);
        }
    }


    public string $commandSignature = 'orders:repair_order_amounts {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();


        $this->handle($order, $command);
    }

}
