<?php

/*
 * author Louis Perez
 * created on 03-03-2026-14h-54m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Exception;
use Illuminate\Console\Command;

class ShowProductsWithMismatchTradeUnits 
{
    use WithActionUpdate;

    protected function handle(MasterShop $masterShop, Command $command): void
    {
        MasterAsset::where('master_shop_id', $masterShop->id)->where('type', MasterAssetTypeEnum::PRODUCT)
            ->orderBy('id')
            ->chunkById(1000, function ($masterProducts) use ($command) {
                foreach ($masterProducts as $masterProduct) {

                    $masterAssetTradeUnits = $masterProduct->tradeUnits->pluck('pivot.quantity', 'id');

                    $products = $masterProduct->products;
                    foreach ($products as $product) {
                        $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

                        $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
                        $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

                        if ($diffFromMaster->isNotEmpty() || $diffFromProduct->isNotEmpty()) {
                            echo "\n";
                            echo "====================================================\n";
                            echo "MASTER PRODUCT ID: {$masterProduct->code} | {$masterProduct->slug}\n";
                            echo "PRODUCT ID:        {$product->code} | {$product->slug}\n";
                            echo "----------------------------------------------------\n";

                            echo "MASTER UNITS:\n";
                            foreach ($masterAssetTradeUnits as $id => $qty) {
                                echo "  - TradeUnit {$id}: {$qty}\n";
                            }

                            echo "\nPRODUCT UNITS:\n";
                            foreach ($productTradeUnits as $id => $qty) {
                                echo "  - TradeUnit {$id}: {$qty}\n";
                            }

                            if ($diffFromMaster->isNotEmpty()) {
                                echo "\nMissing / Different From Master:\n";
                                foreach ($diffFromMaster as $id => $qty) {
                                    echo "  - TradeUnit {$id}: Master=$qty, Product=".
                                        ($productTradeUnits[$id] ?? 'N/A')."\n";
                                }
                            }

                            if ($diffFromProduct->isNotEmpty()) {
                                echo "\nExtra / Different In Product:\n";
                                foreach ($diffFromProduct as $id => $qty) {
                                    echo "  - TradeUnit {$id}: Product={$qty}, Master=".
                                        ($masterAssetTradeUnits[$id] ?? 'N/A')."\n";
                                }
                            }

                            echo "====================================================\n\n";

                            if ($command->confirm('Do you want to repair this product?', true)) {
                                $this->repairTradeUnits($masterProduct, $product);
                            }
                        }
                    }
                }
            });
    }

    public function repairTradeUnits(MasterAsset $masterProduct, Product $product)
    {
        $tradeUnitData = [];
        foreach($masterProduct->tradeUnits as $tradeUnit) {
            array_push($tradeUnitData, [
                'id'       => $tradeUnit->id,
                'quantity' => data_get($tradeUnit, 'pivot.quantity'),
            ]);
        }
        SyncProductTradeUnits::run($product, $tradeUnitData);
        echo "$tradeUnit->id | Repaired -- OK\n";
    }

    public string $commandSignature = 'show:products_with_mismatch_trade_units {masterShop}';

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))->firstOrFail();
        $this->handle($masterShop, $command);
    }

}
