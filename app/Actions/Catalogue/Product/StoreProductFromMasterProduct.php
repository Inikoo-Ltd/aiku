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
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

class StoreProductFromMasterProduct extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, array $modelData): void
    {
        $productCategories = $masterAsset->masterFamily->productCategories;

        if ($productCategories) {
            foreach ($productCategories as $productCategory) {
                $shop = $productCategory->shop;
                if (isset($modelData['shop_products']) && !array_key_exists($shop->id, $modelData['shop_products'])) {
                    continue;
                }

                $shopProductData = isset($modelData['shop_products'][$shop->id]) ? $modelData['shop_products'][$shop->id] : [];
                $price           = $shopProductData['price'] ?? $masterAsset->price;
                $rrp             = $shopProductData['rrp'] ?? $price * 2.4;
                $createWebpage   = !isset($shopProductData['create_webpage']) || $shopProductData['create_webpage'];

                $orgStocks = [];


                foreach ($masterAsset->stocks as $stock) {
                    $stockOrgStock = $stock->orgStocks()->where('organisation_id', $productCategory->organisation_id)->first();


                    if (!$stockOrgStock) {
                        $stockOrgStock = StoreOrgStock::make()->action(
                            $productCategory->organisation,
                            $stock,
                            [
                                'state' => OrgStockStateEnum::ACTIVE,
                            ]
                        );
                    }
                    $orgStocks[$stockOrgStock->id] = [
                        'quantity' => $stock->pivot->quantity,
                    ];
                }

                $data = [
                    'code'              => $masterAsset->code,
                    'name'              => $masterAsset->name,
                    'price'             => $price,
                    'rrp'               => $rrp,
                    'unit'              => $masterAsset->unit,
                    'units'             => $masterAsset->units,
                    'is_main'           => true,
                    'org_stocks'        => $orgStocks,
                    'master_product_id' => $masterAsset->id,
                    'state'             => ProductStateEnum::ACTIVE,
                    'status'            => ProductStatusEnum::FOR_SALE,
                    'is_for_sale'       => true,
                    'marketing_dimensions' => $masterAsset->marketing_dimensions,
                    'gross_weight'  => $masterAsset->gross_weight,
                    'marketing_weight' => $masterAsset->marketing_weight
                ];

                $product = Product::where('shop_id', $shop->id)
                    ->whereRaw("lower(code) = lower(?)", [$masterAsset->code])
                    ->first();

                if ($product) {
                    data_set($data, 'family_id', $productCategory->id);
                    data_set($data, 'well_formatted_org_stocks', $orgStocks);
                    data_forget($data, 'org_stocks');


                    $this->updateFoundProduct($product, $data, $createWebpage);
                    continue;
                }

                $product = StoreProduct::run($productCategory, $data);

                $product->refresh();
                CloneProductImagesFromTradeUnits::run($product);
                $product->refresh();

                if ($createWebpage) {
                    $webpage = StoreProductWebpage::run($product);
                    PublishWebpage::make()->action($webpage, [
                        'comment' => 'first publish'
                    ]);
                }
            }
        }
    }

    public function updateFoundProduct(Product $product, array $data, bool $createWebpage): void
    {

        $product = UpdateProduct::run($product, $data);
        CloneProductImagesFromTradeUnits::run($product);
        $product->refresh();
        if ($createWebpage && $product->webpage === null) {
            $webpage = StoreProductWebpage::run($product);
            PublishWebpage::make()->action($webpage, [
                'comment' => 'first publish'
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'shop_products' => ['sometimes', 'array']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true): void
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterAsset->group;

        $this->initialisation($group, $modelData);

        $this->handle($masterAsset, $this->validatedData);
    }

}
