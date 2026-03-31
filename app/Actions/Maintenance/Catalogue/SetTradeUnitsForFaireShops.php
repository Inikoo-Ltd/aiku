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
use Symfony\Component\Console\Helper\ProgressBar;

class SetTradeUnitsForFaireShops
{
    use WithActionUpdate;


    public function handle(Product $product, Command $command): void
    {
        $code       = $product->code;
        $seederShop = $product->shop->seederShop;
        if (!$seederShop) {
            return;
        }


        $seederProduct = Product::whereRaw("lower(code) = lower(?)", [$code])->where('shop_id', $seederShop->id)->first();


        if ($seederProduct) {
            $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

            if ($product->shop->slug == 'awfe') {
                $masterAssetTradeUnits = $seederProduct->tradeUnits->pluck('pivot.quantity', 'id')->forget(42894);
            } else {
                $masterAssetTradeUnits = $seederProduct->tradeUnits->pluck('pivot.quantity', 'id');
            }

            $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
            $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

            if (($diffFromMaster->isNotEmpty() || $diffFromProduct->isNotEmpty()) && $masterAssetTradeUnits->count() == 1) {
                $getNumberUnits = $masterAssetTradeUnits->first();


                if ($product->units == $seederProduct->units && $product->units == $getNumberUnits) {
                    $command->warn("Product  ".$product->slug.' '.$product->units.' Seeder ');


                    $tradeUnitsData = [];
                    foreach ($seederProduct->tradeUnits as $tradeUnit) {
                        if ($tradeUnit->slug != 'ial01') {
                            $tradeUnitsData[] = [
                                'id'       => $tradeUnit->id,
                                'quantity' => $tradeUnit->pivot->quantity
                            ];
                        }
                    }


                    if (!empty($tradeUnitsData)) {
                        UpdateTradeUnitsForExternalProduct::make()->action($product, [
                            'trade_units' => $tradeUnitsData
                        ]);
                    }
                }
            }
        } else {
            //$command->error("Product not found in seeder ".$product->code);
        }
    }


    public string $commandSignature = 'repair:set_trade_units_for_faire_shops {faire_shop?} {--in_process=false} {--product=}';

    public function asCommand(Command $command): int
    {
        if ($command->option('product')) {
            $product = Product::where('slug', $command->option('product'))->firstOrFail();
            $this->handle($product, $command);
            exit;
        }

        $faireShop  = Shop::where('slug', $command->argument('faire_shop'))->firstOrFail();
        $seederShop = $faireShop->seederShop;
        if (!$seederShop) {
            $command->error("Seeder shop not found for ".$faireShop->name);

            return 1;
        }

        $countQuery = Product::where('shop_id', $faireShop->id);
        //        if ($command->option('in_process')) {
        //            $countQuery->where('state', ProductStateEnum::IN_PROCESS);
        //        }

        $count = $countQuery->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('aiku_eta');
        $bar->start();

        $query = Product::where('shop_id', $faireShop->id);


        $query->orderBy('id')
            ->chunk(100, function (Collection $products) use ($bar, $command) {
                foreach ($products as $product) {
                    $this->handle($product, $command);
                    $bar->advance();
                }
            });

        return 0;
    }
}
