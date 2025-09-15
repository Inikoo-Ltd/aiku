<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Aug 2025 21:22:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnused */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\MatchAssetsToMaster;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingMasterAssets
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop, Command $command): void
    {
        $seederShop = $this->getSeederShop($masterShop);

        Product::where('shop_id', $seederShop->id)->orderBy('id')
            ->chunk(1000, function ($models) use ($command, $masterShop) {
                foreach ($models as $product) {
                    $asset   = MatchAssetsToMaster::run($product->asset, $masterShop);
                    $product = $asset->product;

                    if ($product->is_main && !$product->master_product_id) {
                        $command->info("Found main product with no master asset $product->slug");
                        $this->upsertMasterProduct($masterShop, $product);
                    }
                }
            });
    }

    /**
     * @throws \Throwable
     */
    public function upsertMasterProduct(MasterShop $masterShop, Product $product): ?MasterAsset
    {
        $code = $product->code;


        $foundMasterAssetData = DB::table('master_assets')
            ->where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();

        $foundMasterProduct = null;


        if (!$foundMasterAssetData) {
            $masterFamily = $this->getMasterFamily($masterShop, $product);

            $exchange = GetCurrencyExchange::make()->run(group()->currency, $product->shop->currency);

            $price = $product->price * $exchange;

            $foundMasterProduct = StoreMasterAsset::make()->action(
                $masterFamily ?? $masterShop,
                [
                    'code'        => $product->code,
                    'name'        => $product->name,
                    'description' => $product->description,
                    'type'        => MasterAssetTypeEnum::PRODUCT,
                    'price'       => $price
                ]
            );

        } else {
            $foundMasterProduct = MasterAsset::find($foundMasterAssetData->id);

            $dataToUpdate = [
                'code' => $product->code,
                'name' => $product->name,
            ];
            if ($product->description && !$foundMasterProduct->description) {
                data_set($dataToUpdate, 'description', $product->description);
            }

            $foundMasterProduct = UpdateMasterAsset::make()->action(
                $foundMasterProduct,
                $dataToUpdate
            );
        }


        $markForDiscontinued = false;
        $status              = true;
        $maskForDiscontinued = null;
        $discontinuedAt      = null;


        if ($product->state == ProductStateEnum::DISCONTINUED) {
            $status              = false;
            $discontinuedAt      = $product->discontinued_at;
            $maskForDiscontinued = $product->mark_for_discontinued_at;
        }

        if ($product->state == ProductCategoryStateEnum::DISCONTINUING) {
            $markForDiscontinued = true;
            $maskForDiscontinued = $product->mark_for_discontinued_at;
        }



        UpdateMasterAsset::run(
            $foundMasterProduct,
            [
                'status'                   => $status,
                'mark_for_discontinued'    => $markForDiscontinued,
                'mark_for_discontinued_at' => $maskForDiscontinued,
                'discontinued_at'          => $discontinuedAt,
            ]
        );


        return $foundMasterProduct;
    }


    /**
     * @throws \Throwable
     */
    public function getMasterFamily(MasterShop $masterShop, Product $product): ?MasterProductCategory
    {
        $masterFamily = null;
        if ($product->family) {
            $masterFamily = AddMissingProductCategoriesToMaster::make()
                ->upsertMasterFamily($masterShop, $product->family);
        }

        return $masterFamily;
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
        return 'repair:add_missing_master_products';
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
