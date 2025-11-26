<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:18 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductPricesAndUnits
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, MasterShop|Shop $shop, Command $command = null): void
    {
        $shop->products()
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($fromShop, $command) {
                foreach ($products as $product) {
                    $foundFromProduct = DB::table('products')
                        ->where('shop_id', $fromShop->id)
                        ->whereRaw("lower(code) = lower(?)", [$product->code])
                        ->first();

                    if ($foundFromProduct) {
                        $fromProduct = Product::find($foundFromProduct->id);

                        if ($fromProduct) {
                            $this->updatePriceAndUnits($fromProduct, $product, $command);
                        }
                    }
                }
            });
    }

    public function updatePriceAndUnits(Product $fromProduct, Product $product, Command $command = null): void
    {
        $dataToUpdate = [];
        if ($product->price != $fromProduct->price) {
            $command?->info("Updating price for product $product->code from $product->price to $fromProduct->price");
        }

        if ($product->rrp != $fromProduct->rrp) {
            $command?->info("Updating rrp for product $product->code from $product->rrp to $fromProduct->rrp");
        }
        data_set($dataToUpdate, 'rrp', $fromProduct->rrp);
        data_set($dataToUpdate, 'price', $fromProduct->price);


        data_set($dataToUpdate, 'units', $fromProduct->units);

        $tradeUnits = [];
        foreach ($fromProduct->tradeUnits as $tradeUnit) {
            $tradeUnits[$tradeUnit->id] = [
                'quantity' => $tradeUnit->pivot->quantity,
            ];
        }
        data_set($dataToUpdate, 'trade_units', $tradeUnits);

       // print_r($dataToUpdate);

        UpdateProduct::make()->action($product, $dataToUpdate);
    }


    public function getCommandSignature(): string
    {
        return 'shop:copy_prices_and_units {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $toShop = Shop::where('slug', $command->argument('to'))->firstOrFail();

        $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();

        $this->handle($fromShop, $toShop, $command);

        return 0;
    }


}
