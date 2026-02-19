<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 Aug 2025 22:18:22 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $masterAssetID): string
    {
        return $masterAssetID ?? 'empty';
    }

    public function handle(int $masterAssetID): void
    {
        $masterAsset = MasterAsset::find($masterAssetID);
        if (!$masterAsset) {
            return;
        }

        $numberAssetsFromClosedShops = DB::table('assets')
            ->join('shops', 'shops.id', '=', 'assets.shop_id')
            ->where('master_asset_id', $masterAsset->id)
            ->where('shops.state', '!=', ShopStateEnum::CLOSED)->count();

        if ($masterAsset->type == MasterAssetTypeEnum::PRODUCT) {
            $numberCurrentAssets = DB::table('products')
                ->join('shops', 'shops.id', '=', 'products.shop_id')
                ->where('master_product_id', $masterAsset->id)
                ->where('is_for_sale', true)
                ->where('shops.state', '!=', ShopStateEnum::CLOSED)
                ->whereIn('products.state', [
                    ProductStateEnum::IN_PROCESS,
                    ProductStateEnum::ACTIVE,
                    ProductStateEnum::DISCONTINUING,
                ])->count();

            $numberAssets = DB::table('products')
                ->join('shops', 'shops.id', '=', 'products.shop_id')
                ->where('master_product_id', $masterAsset->id)
                ->where('shops.state', '!=', ShopStateEnum::CLOSED)
                ->count();

            $totalAssets = DB::table('products')
                ->where('master_product_id', $masterAsset->id)
                ->count();


            $numberProductsIsNotForSale =  DB::table('products')
                ->join('shops', 'shops.id', '=', 'products.shop_id')
                ->where('master_product_id', $masterAsset->id)
                ->where('is_for_sale', false)
                ->where('shops.state', '!=', ShopStateEnum::CLOSED)
                ->whereIn('products.state', [
                    ProductStateEnum::IN_PROCESS,
                    ProductStateEnum::ACTIVE,
                    ProductStateEnum::DISCONTINUING,
                ])->count();
        } else {
            $numberCurrentAssets = DB::table('assets')->where('master_asset_id', $masterAsset->id)
                ->whereIn('state', [
                    AssetStateEnum::IN_PROCESS,
                    AssetStateEnum::ACTIVE,
                    AssetStateEnum::DISCONTINUING,
                ])->count();
            $numberAssets = DB::table('assets')->where('master_asset_id', $masterAsset->id)->count();
            $totalAssets = $numberAssets;
            $numberProductsIsNotForSale = 0;
        }


        $stats = [
            'number_assets_including_closed_shops' => $totalAssets,
            'number_assets'                        => $numberAssets,
            'number_current_assets'                => $numberCurrentAssets,
            'number_assets_forced_not_for_sale'    => $numberProductsIsNotForSale,
            'number_assets_from_closed_shops'      => $numberAssetsFromClosedShops
        ];

        //print_r($stats);

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'state',
                enum: AssetStateEnum::class,
                models: Asset::class,
                where: function ($q) use ($masterAsset) {
                    $q->where('master_asset_id', $masterAsset->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'assets',
                field: 'type',
                enum: AssetTypeEnum::class,
                models: Asset::class,
                where: function ($q) use ($masterAsset) {
                    $q->where('master_asset_id', $masterAsset->id);
                }
            )
        );

        if ($masterAsset->is_for_sale) {
            $status = $stats['number_current_assets'] > 0;
            UpdateMasterAsset::run($masterAsset, ['status' => $status]);
        }


        $masterAsset->stats()->update($stats);
    }


}
