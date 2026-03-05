<?php

/*
 * author Louis Perez
 * created on 03-03-2026-14h-54m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;

class ShowProductsWithMismatchTradeUnits
{
    use WithActionUpdate;

    protected function handle(MasterShop $masterShop, Command $command): void
    {
        MasterAsset::where('master_shop_id', $masterShop->id)->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true)
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
                                $tradeUnit = TradeUnit::find($id);
                                echo "  - TradeUnit $tradeUnit->slug: {$qty}\n";
                            }

                            echo "\nPRODUCT UNITS:\n";
                            foreach ($productTradeUnits as $id => $qty) {
                                $tradeUnit = TradeUnit::find($id);
                                echo "  - TradeUnit $tradeUnit->slug: {$qty}\n";
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
                            echo "1. Follow master data (default)\n";
                            echo "2. Follow children data [{$product->shop->slug}]\n";
                            echo "3. Do nothing\n\n";

                            switch($command->ask("option: ", '3')){
                                case "1":
                                    $this->copyMasterToProducts($masterProduct);
                                    break;
                                case "2":
                                    $this->copyProductToMaster($product);
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            });
    }


    public function copyProductToMaster(Product $product): void
    {
        $tradeUnits = $product->tradeUnits
            ->map(function ($tradeUnit) {
                $tradeUnit->quantity = $tradeUnit->pivot->quantity;
                return $tradeUnit;
            })->toArray();

        UpdateMasterAsset::run($product->masterProduct, [
            'trade_units' => $tradeUnits
        ]);

        echo "{$product->masterProduct->slug} | Repaired --  Product -> Master OK\n";
    }

    public function copyMasterToProducts(MasterAsset $masterProduct): void
    {
        $tradeUnitData = [];
        foreach ($masterProduct->tradeUnits as $tradeUnit) {
            $tradeUnitData[] = [
                'id'       => $tradeUnit->id,
                'quantity' => data_get($tradeUnit, 'pivot.quantity'),
            ];
        }

        foreach ($masterProduct->products as $product) {
            SyncProductTradeUnits::run($product, $tradeUnitData);
        }


        echo "$masterProduct->slug | Repaired -- OK\n";
    }

    public string $commandSignature = 'show:products_with_mismatch_trade_units {masterShop}';

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))->firstOrFail();
        $this->handle($masterShop, $command);
    }

}
