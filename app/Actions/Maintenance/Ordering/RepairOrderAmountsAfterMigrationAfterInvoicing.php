<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RepairOrderAmountsAfterMigrationAfterInvoicing
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order, Command $command): void
    {
        $updateInvoice = false;
        $invoice       = $order->invoices()->first();
        foreach ($order->transactions as $transaction) {
            if ($transaction->model_type != 'Product') {
                continue;
            }


            /** @var \App\Models\Catalogue\Product $product */
            $product = $transaction->model;


            $newHistoricAssetId = $product->current_historic_asset_id;
            $transaction->update([
                'historic_asset_id' => $newHistoricAssetId,
                'gross_amount'      => $product->currentHistoricProduct->price * $transaction->quantity_ordered,
                'net_amount'        => $product->currentHistoricProduct->price * $transaction->quantity_ordered,
            ]);

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();


            if ($invoiceTransaction) {
                $updateInvoice                     = true;
                $dataToUpdate                      = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction);
                $dataToUpdate['historic_asset_id'] = $newHistoricAssetId;
                $invoiceTransaction->update($dataToUpdate);
            }
        }

        CalculateOrderTotalAmounts::run($order);
        if ($invoice && $updateInvoice) {
            CalculateInvoiceTotals::run($invoice);
        }
    }


    public string $commandSignature = 'orders:repair_order_amounts_after_invoice {order}';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();


        $this->handle($order, $command);
    }

}
