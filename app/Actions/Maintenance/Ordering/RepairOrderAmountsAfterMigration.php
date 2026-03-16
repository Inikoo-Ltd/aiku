<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 15:05:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Ordering;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RepairOrderAmountsAfterMigration
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Order $order, Command $command): void
    {
        $updateInvoice = false;
        $invoice       = $order->invoices()->first();
        if ($invoice) {
            return;
        }

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

                $diff = abs($currentGross - $newGrossAmount);
                if ($diff > 0.00001) {
                    $command->error("Product: $product->slug - old net amount: $currentGross - new net amount: $newGrossAmount");
                } else {
                    $command->info("Product: $product->slug - old historic asset id: $oldHistoricAssetId - new historic asset id: $newHistoricAssetId");
                    $transaction->update([
                        'historic_asset_id' => $newHistoricAssetId,
                    ]);
                }
            }
        }
    }


    public string $commandSignature = 'orders:repair_order_amounts {order?}  {--S|shop= shop slug}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('order')) {
            $order = Order::where('slug', $command->argument('order'))->firstOrFail();
            $command->info("Processing Order: $order->slug");
            $this->handle($order, $command);

            return;
        }

        if ($command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->firstOrFail();

            foreach (
                Order::where('shop_id', $shop->id)->whereNotIn('state', [
                    OrderStateEnum::CANCELLED,
                    OrderStateEnum::CREATING,
                    OrderStateEnum::FINALISED,
                    OrderStateEnum::DISPATCHED
                ])->get() as $order
            ) {
                $this->handle($order, $command);
            }
        }
    }

}
