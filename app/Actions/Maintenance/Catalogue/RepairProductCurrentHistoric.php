<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Mar 2026 22:53:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Product\UpdateOrdersInBasketsAfterProductUpdated;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairProductCurrentHistoric
{
    use WithActionUpdate;


    public function handle(Product $product, Command $command): void
    {
        if (
            $product->price != $product->currentHistoricProduct->price
            || $product->name != $product->currentHistoricProduct->name
            || $product->code != $product->currentHistoricProduct->code
            || $product->units != $product->currentHistoricProduct->units
            || $product->unit != $product->currentHistoricProduct->unit


        ) {
            $command->line('P: '.$product->slug.' mismatch ');
            if ($product->price != $product->currentHistoricProduct->price) {
                $command->line('P: '.$product->slug.' price mismatch '.$product->price.' >>  '.$product->currentHistoricProduct->price);
            }
            if ($product->name != $product->currentHistoricProduct->name) {
                $command->line('P: '.$product->slug.' name mismatch '.$product->name.' >> '.$product->currentHistoricProduct->name);
            }

            if ($product->code != $product->currentHistoricProduct->code) {
                $command->line('P: '.$product->slug.' code mismatch '.$product->code.' >> '.$product->currentHistoricProduct->code);
            }

            if ($product->units != $product->currentHistoricProduct->units) {
                $command->line('P: '.$product->slug.' units mismatch '.$product->units.' >> '.$product->currentHistoricProduct->units);
            }

            if ($product->unit != $product->currentHistoricProduct->unit) {
                $command->line('P: '.$product->slug.' unit mismatch '.$product->unit.' >> '.$product->currentHistoricProduct->unit);
            }


            $historicAsset = StoreHistoricAsset::run($product, []);

            $product->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
            UpdateOrdersInBasketsAfterProductUpdated::dispatch($product->id);
        }
    }


    public string $commandSignature = 'repair:product_current_historic {product?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('product')) {
            $product = Product::find($command->argument('product'));
            $this->handle($product, $command);
        } else {
            $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

            $count = Product::whereIn('shop_id', $aikuShops)->count();

            $bar = $command->getOutput()->createProgressBar($count);
            $bar->setFormat('debug');
            $bar->start();

            Product::whereIn('shop_id', $aikuShops)->orderBy('id')
                ->chunk(100, function (Collection $models) use ($bar, $command) {
                    foreach ($models as $model) {
                        $this->handle($model, $command);
                        $bar->advance();
                    }
                });
        }
    }

}
