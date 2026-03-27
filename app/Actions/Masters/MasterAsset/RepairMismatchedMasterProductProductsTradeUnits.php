<?php

/*
 * author Louis Perez
 * created on 03-03-2026-14h-54m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMismatch;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;

class RepairMismatchedMasterProductProductsTradeUnits
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
                        if ($product->shop_id != 18) {
                            continue;
                        }

                        if ($product->state == ProductStateEnum::DISCONTINUED) {
                            continue;
                        }

                        if (!$product->is_for_sale) {
                            continue;
                        }




                        $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');

                        $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
                        $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

                        if ($diffFromMaster->isNotEmpty() || $diffFromProduct->isNotEmpty()) {
                            echo "\n";
                            echo "====================================================\n";
                            echo "MASTER PRODUCT ID: $masterProduct->code | $masterProduct->slug | $masterProduct->id \n";
                            echo "PRODUCT ID:        $product->code | $product->slug | $product->id \n";
                            echo "----------------------------------------------------\n";

                            echo "MASTER UNITS:\n";
                            $autoSkip         = false;
                            $autoShopToMaster = false;
                            foreach ($masterAssetTradeUnits as $id => $qty) {
                                $tradeUnit = TradeUnit::find($id);
                                echo "  - TradeUnit $tradeUnit->slug: $qty\n";
                                if (str_starts_with($tradeUnit->slug, 'abot') || str_starts_with($tradeUnit->slug, 'gbot')
                                    || str_starts_with($tradeUnit->slug, 'rdbot-')
                                    || str_starts_with($tradeUnit->slug, 'gjar-')
                                    || str_starts_with($tradeUnit->slug, 'actc-')
                                    || str_starts_with($tradeUnit->slug, 'opp-')
                                    || str_starts_with($tradeUnit->slug, 'sellp-')
                                    || str_starts_with($tradeUnit->slug, 'fgb-')
                                    || str_starts_with($tradeUnit->slug, 'gemfr-')
                                    || str_starts_with($tradeUnit->slug, 'salt-')
                                    || str_starts_with($tradeUnit->slug, 'qsalt-')
                                    || str_starts_with($tradeUnit->slug, 'ncl-')
                                    || str_starts_with($tradeUnit->slug, 'wwib-')



                                ) {
                                    $autoSkip = true;
                                }

                                if (in_array($tradeUnit->slug, ['sais-mx'])) {
                                    $autoSkip = true;
                                }

                                //MGBS-ST


                                if ($tradeUnit->slug == 'ial01') {
                                    $autoShopToMaster = true;
                                }
                            }

                            echo "\nPRODUCT UNITS:\n";
                            foreach ($productTradeUnits as $id => $qty) {
                                $tradeUnit = TradeUnit::find($id);
                                if (str_starts_with($tradeUnit->slug, 'abot')
                                    || str_starts_with($tradeUnit->slug, 'gbot-')
                                    || str_starts_with($tradeUnit->slug, 'rdbot-')
                                    || str_starts_with($tradeUnit->slug, 'gjar-')
                                    || str_starts_with($tradeUnit->slug, 'actc-')
                                    || str_starts_with($tradeUnit->slug, 'opp-')
                                    || str_starts_with($tradeUnit->slug, 'sellp-')
                                    || str_starts_with($tradeUnit->slug, 'fgb-')
                                    || str_starts_with($tradeUnit->slug, 'gemfr-')
                                    || str_starts_with($tradeUnit->slug, 'salt-')
                                    || str_starts_with($tradeUnit->slug, 'qsalt-')
                                    || str_starts_with($tradeUnit->slug, 'ncl-')

                                ) {
                                    $autoSkip = true;
                                }
                                echo "  - TradeUnit $tradeUnit->slug: $qty\n";
                            }

                            if ($diffFromMaster->isNotEmpty()) {
                                echo "\nMissing / Different From Master:\n";
                                foreach ($diffFromMaster as $id => $qty) {
                                    echo "  - TradeUnit $id: Master=$qty, Product=".
                                        ($productTradeUnits[$id] ?? 'N/A')."\n";
                                }
                            }

                            if ($diffFromProduct->isNotEmpty()) {
                                echo "\nExtra / Different In Product:\n";
                                foreach ($diffFromProduct as $id => $qty) {
                                    echo "  - TradeUnit $id: Product=$qty, Master=".
                                        ($masterAssetTradeUnits[$id] ?? 'N/A')."\n";
                                }
                            }

                            if ($autoSkip) {
                                echo "\nAuto Skip\n";
                                continue;
                            }

                            if ($autoShopToMaster) {
                                echo "\nAuto repair =======".$product->code."=====. \n";
                                $this->copyProductToMaster($product);
                                continue;
                            }

                            echo "====================================================\n\n";
                            echo "1. Follow master data (default)\n";
                            echo "2. Follow children data [{$product->shop->slug}]\n";
                            echo "3. Do nothing\n\n";

                            switch ($command->ask("option: ", '2')) {
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

        MasterAssetHydrateMismatch::run($product->masterProduct);
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

        MasterAssetHydrateMismatch::run($masterProduct);
        echo "$masterProduct->slug | Repaired -- OK\n";
    }

    public string $commandSignature = 'repair_mismatched_master_product_products_trade_units {masterShop}';

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('masterShop'))->firstOrFail();
        $this->handle($masterShop, $command);
    }

}
