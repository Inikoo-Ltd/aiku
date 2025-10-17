<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Oct 2025 17:13:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */



/** @noinspection PhpUnused */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetsTradeUnitsFromProducts
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop, Command $command): void
    {

        $seederShop = $this->getSeederShop($masterShop);

        MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('code', 'VRUG-23')
            ->where('type', MasterAssetTypeEnum::PRODUCT)->orderBy('id')
            ->chunk(1000, function ($models) use ($command, $seederShop, $masterShop) {
                foreach ($models as $masterProduct) {




                    $product = Product::where('shop_id', $seederShop->id)->where('master_product_id', $masterProduct->id)->first();

                    if ($product) {
                        $product = Product::where('master_product_id', $masterProduct->id)->first();

                    }

                    if ($product) {
                        $tradeUnits = $product->tradeUnits()->withPivot('quantity')->get();
                        $tradeUnitData = [];
                        /** @var TradeUnit $tradeUnit */
                        foreach ($tradeUnits as $tradeUnit) {
                            $tradeUnitData[] = [
                                'id' => $tradeUnit->id,
                                'quantity' => $tradeUnit->pivot->quantity,
                            ];
                        }

                        StoreMasterAsset::make()->processTradeUnits($masterProduct, $tradeUnitData);




                    } else {

                        $tradeUnits = $masterProduct->tradeUnits()->withPivot('quantity')->get();
                        $tradeUnitData = [];
                        /** @var TradeUnit $tradeUnit */
                        foreach ($tradeUnits as $tradeUnit) {
                            $tradeUnitData[] = [
                                'id' => $tradeUnit->id,
                                'quantity' => $tradeUnit->pivot->quantity,
                            ];
                        }

                        dd($tradeUnitData);


                        $command->info("$masterShop->slug  Product not found for master product $masterProduct->id $masterProduct->code");
                    }

                }
            });
    }


    public function getSeederShop(MasterShop $masterShop): Shop
    {
        $shopId = match ($masterShop->slug) {
            'aw' => 1,
            'ds' => 13,
            'ac' => 9,
            'aroma' => 40,
            'ful' => 15
        };

        return Shop::find($shopId);
    }


    public function getCommandSignature(): string
    {
        return 'repair:master_assets_trade_units_from_products';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {



        MasterShop::orderBy('id')
            ->chunk(1000, function ($models) use ($command) {
                foreach ($models as $model) {
                    $this->handle($model, $command);
                }
            });


        return 0;
    }


}
