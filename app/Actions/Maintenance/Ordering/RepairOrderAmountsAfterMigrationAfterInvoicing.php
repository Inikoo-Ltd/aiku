<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairOrderAmountsAfterMigrationAfterInvoicing
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithOrganisationSource;

    public function handle(Order $order, Command $command): void
    {
        $organisation = $order->organisation;

        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $updateInvoice = false;
        $invoice       = $order->invoices()->first();
        foreach ($order->transactions as $transaction) {
            if ($transaction->model_type != 'Product') {
                continue;
            }
            $discountFactor = 1;

            /** @var \App\Models\Catalogue\Product $product */
            $product = $transaction->model;
            $newHistoricAssetId = $product->current_historic_asset_id;
            $sourceID = $transaction->source_id;

            $grossAmount = $product->currentHistoricProduct->price * $transaction->quantity_ordered;
            $netAmount   = $product->currentHistoricProduct->price * $transaction->quantity_ordered * $discountFactor;

            if ($sourceID) {
                $sourceID = explode(':', $sourceID);

                if (is_array($sourceID) and count($sourceID) == 2) {
                    $auData = DB::connection('aurora')->table('Order Transaction Fact')
                        ->where('Order Transaction Fact Key', $sourceID[1])->first();


                    if ($auData) {
                        $gross = $auData->{'Order Transaction Gross Amount'};

                        $productPrice = $gross / $transaction->quantity_ordered;


                        $historicProduct = HistoricAsset::where('model_type', 'Product')->where('model_id', $product->id)
                            ->where('price', $productPrice)
                            ->where('units', $product->units)
                            ->first();

                        if (!$historicProduct) {
                            $historicProduct = StoreHistoricAsset::run(
                                assetModel: $product,
                                modelData: [
                                    'price' => $productPrice,
                                ]
                            );
                        }

                        $newHistoricAssetId = $historicProduct->id;

                        $grossAmount = $auData->{'Order Transaction Gross Amount'};
                        $netAmount   = $auData->{'Order Transaction Amount'};
                    }
                }
            }


            $transaction->update([
                'historic_asset_id' => $newHistoricAssetId,
                'gross_amount'      => $grossAmount,
                'net_amount'        => $netAmount,
            ]);

            $invoiceTransaction = InvoiceTransaction::where('transaction_id', $transaction->id)->first();


            if ($invoiceTransaction) {
                $updateInvoice = true;
                $dataToUpdate  = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction);

                $transaction->update([
                    'gross_amount' => $dataToUpdate['gross_amount'],
                    'net_amount'   => $dataToUpdate['net_amount'],
                ]);

                $dataToUpdate['historic_asset_id'] = $newHistoricAssetId;
                $invoiceTransaction->update($dataToUpdate);
            }
        }

        CalculateOrderTotalAmounts::run($order);
        if ($invoice && $updateInvoice) {
            CalculateInvoiceTotals::run($invoice);
        }
    }


    public string $commandSignature = 'orders:repair_order_amounts_after_invoice {order} ';

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();


        $this->handle($order, $command);
    }

}
