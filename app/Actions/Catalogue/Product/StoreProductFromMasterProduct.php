<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\GrpAction;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

class StoreProductFromMasterProduct extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, array $modelData)
    {
        $productCategories = $masterAsset->masterFamily->productCategories;

        if ($productCategories) {
            foreach ($productCategories as $productCategory) {
                $shop = $productCategory->shop;
                if (isset($modelData['shop_products']) && !array_key_exists($shop->id, $modelData['shop_products'])) {
                    continue;
                }

                $shopProductData = isset($modelData['shop_products'][$shop->id]) ? $modelData['shop_products'][$shop->id] : [];
                $customPrice = isset($shopProductData['price']) ? $shopProductData['price'] : null;
                $createWebpage = isset($shopProductData['create_webpage']) ? $shopProductData['create_webpage'] : true;

                $orgStocks = [];
                foreach ($masterAsset->stocks as $stock) {
                    $stockOrgStocks = $stock->orgStocks()->where('organisation_id', $productCategory->organisation_id)->get();

                    if ($stockOrgStocks->isEmpty()) {

                        StoreOrgStock::make()->action(
                            $productCategory->organisation,
                            $stock,
                            []
                        );
                        $stockOrgStocks = $stock->orgStocks()->where('organisation_id', $productCategory->organisation_id)->get();

                    }

                    foreach ($stockOrgStocks as $orgStock) {
                        $orgStocks[$orgStock->id] = [
                            'quantity' => $orgStock->quantity_in_locations,
                        ];
                    }
                }

                $data = [
                    'code' => $masterAsset->code,
                    'name' => $masterAsset->name,
                    'price' => $customPrice ?? $masterAsset->price,
                    'unit'    => $masterAsset->unit,
                    'is_main' => true,
                    'org_stocks'  => $orgStocks,
                    'master_product_id' => $masterAsset->id,
                    'state' => ProductStateEnum::ACTIVE,
                    'status' => ProductStatusEnum::FOR_SALE,
                    'is_for_sale' => true
                ];

                $product = StoreProduct::run($productCategory, $data);
                $product->refresh();

                if ($createWebpage) {
                    $webpage = StoreProductWebpage::run($product);
                    PublishWebpage::make()->action($webpage, [
                        'comment' => 'first publish'
                    ]);
                }
                $tradeUnitsData = [];
                foreach ($masterAsset->tradeUnits as $tradeUnit) {
                    $tradeUnitsData[$tradeUnit->id] = ['quantity' => $tradeUnit->pivot->quantity];
                }
                $product->tradeUnits()->syncWithoutDetaching($tradeUnitsData);

            }
        }
    }

    public function rules(): array
    {
        return [
            'shop_products'            => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true)
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterAsset->group;

        $this->initialisation($group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

}
