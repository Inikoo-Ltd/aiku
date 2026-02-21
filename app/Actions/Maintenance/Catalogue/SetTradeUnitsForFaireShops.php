<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-15h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SetTradeUnitsForFaireShops
{
    use WithActionUpdate;


    public function handle(Product $product, Shop $seederShop, Command $command): void
    {
        $code = $product->code;

        $seederProduct = Product::whereRaw("lower(code) = lower(?)", [$code])->where('shop_id', $seederShop->id)->first();

        if ($seederProduct && $seederProduct->units == $product->units) {
            $tradeUnitsData = [];
            foreach ($seederProduct->tradeUnits as $tradeUnit) {
                $tradeUnitsData[] = [
                    'id'       => $tradeUnit->id,
                    'quantity' => $tradeUnit->pivot->quantity
                ];
            }
            UpdateTradeUnitsForExternalProduct::make()->action($product, [
                'trade_units' => $tradeUnitsData
            ]);

        } elseif (!$seederProduct) {
            $command->error("Product not found in seeder ".$product->code);
        } else {
            $command->error("Product units not match ".$product->code.' '.$product->units.'!='.$seederProduct->units);
        }
    }


    public string $commandSignature = 'repair:set_trade_units_for_faire_shops {seeder} {faire_shop}';

    public function asCommand(Command $command): int
    {
        $faireShop  = Shop::where('slug', $command->argument('faire_shop'))->firstOrFail();
        $seederShop = Shop::where('slug', $command->argument('seeder'))->firstOrFail();

        $count = Product::where('shop_id', $faireShop->id)->where('state', ProductStateEnum::IN_PROCESS)->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Product::where('shop_id', $faireShop->id)->where('state', ProductStateEnum::IN_PROCESS)->orderBy('id')
            ->chunk(100, function (Collection $products) use ($seederShop, $bar, $command) {
                foreach ($products as $product) {
                    $this->handle($product, $seederShop, $command);
                    $bar->advance();
                }
            });

        return 0;
    }
}
