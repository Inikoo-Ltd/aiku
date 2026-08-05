<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Variant\StoreVariantFromMaster;
use App\Actions\OrgAction;
use App\Actions\Helpers\Translations\TranslateModel;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class StoreProductFromMasterProduct extends OrgAction
{
    public string $jobQueue = 'urgent';

    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset, array $modelData, $generateVariant = true, $ignoreCreateWebpage = false): void
    {
        if (!$masterAsset->masterFamily) {
            return;
        }

        foreach ($masterAsset->masterFamily->productCategories as $productCategory) {
            $this->storeInShop($masterAsset, $modelData, $productCategory->shop, $productCategory, $generateVariant, $ignoreCreateWebpage);
        }

        foreach ($this->getExternalShops($masterAsset) as $externalShop) {
            $this->storeInShop($masterAsset, $modelData, $externalShop, null, generateVariant: false, ignoreCreateWebpage: true);
        }
    }

    /**
     * External shops carry no family mirroring the master one, they hang straight off the master shop.
     */
    public function getExternalShops(MasterAsset $masterAsset): Collection
    {
        if (!$masterAsset->masterShop) {
            return new Collection();
        }

        return $masterAsset->masterShop->shops()
            ->where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::FAIRE)
            ->get();
    }

    /**
     * @throws \Throwable
     */
    public function storeInShop(
        MasterAsset $masterAsset,
        array $modelData,
        Shop $shop,
        ?ProductCategory $productCategory,
        bool $generateVariant,
        bool $ignoreCreateWebpage
    ): void {
        if (isset($modelData['shop_products']) && !array_key_exists($shop->id, $modelData['shop_products'])) {
            return;
        }

        $shopProductData = $modelData['shop_products'][$shop->id] ?? [];

        if (Arr::get($shopProductData, 'create_in_shop') != 'Yes') {
            return;
        }

        $price = $masterAsset->getPriceFromCurrency($shop->currency);
        $rrp   = $masterAsset->getRrpFromCurrency($shop->currency);

        $tradeUnits = [];
        foreach ($masterAsset->tradeUnits as $tradeUnit) {
            $tradeUnits[$tradeUnit->id] = [
                'id'       => $tradeUnit->id,
                'quantity' => $tradeUnit->pivot->quantity,
            ];
        }

        $isMain = $masterAsset->is_main;

        $data = [
            'code'              => $masterAsset->code,
            'name'              => $masterAsset->name,
            'description'       => $masterAsset->description,
            'description_title' => $masterAsset->description_title,
            'description_extra' => $masterAsset->description_extra,
            'price'             => $price,
            'rrp'               => $rrp,
            'unit'              => $masterAsset->unit,
            'units'             => $masterAsset->units,
            'trade_units'       => $tradeUnits,
            'master_product_id' => $masterAsset->id,
            'state'             => ProductStateEnum::ACTIVE,
            'status'            => ProductStatusEnum::FOR_SALE,
            'is_main'           => $isMain,
            'is_for_sale'       => data_get($modelData, 'is_for_sale', $masterAsset->is_for_sale),
            'is_minion_variant' => !$isMain,
        ];

        if (count($tradeUnits) > 1) {
            data_set($data, 'gross_weight', $masterAsset->gross_weight);
            data_set($data, 'marketing_weight', $masterAsset->marketing_weight);
        }

        $product = Product::where('shop_id', $shop->id)
            ->whereRaw("lower(code) = lower(?)", [$masterAsset->code])
            ->first();
        if ($product) {
            if ($productCategory) {
                data_set($data, 'family_id', $productCategory->id);
            }
            data_set($data, 'trade_units', $tradeUnits);

            $this->updateFoundProduct($product, $data, ($isMain && !$ignoreCreateWebpage));

            return;
        }

        $product = StoreProduct::run($productCategory ?? $shop, $data);
        $product->refresh();
        CloneProductImagesFromTradeUnits::run($product);
        $product->refresh();

        if ($isMain && !$ignoreCreateWebpage) {
            $webpage = StoreProductWebpage::run($product);
            PublishWebpage::make()->action($webpage, [
                'comment' => 'first publish'
            ]);
        }

        if ($generateVariant) {
            $product = $this->setVariantData($product, $masterAsset);
        }


        TranslateModel::dispatch(
            model: $product,
            translationData: Arr::only($data, [
                'unit',
                'name',
                'description',
                'description_title',
                'description_extra'
            ]),
            overwrite: true
        );
    }

    /**
     * @throws \Throwable
     */
    public function setVariantData(Product $product, MasterAsset $masterAsset): Product
    {
        $masterVariant = $masterAsset->masterVariant;

        if ($masterVariant) {
            $variant = $masterAsset->masterVariant->variants()->where('shop_id', $product->shop->id)->first();
            if (!$variant) {
                $variant = StoreVariantFromMaster::make()->action(
                    $masterAsset->masterVariant,
                    $product->shop,
                    [
                        'data' => $masterAsset->masterVariant->data,
                    ]
                );
            }
            $product->update([
                'variant_id' => $variant->id,
            ]);
            $leader = $masterAsset->masterVariant->leaderMasterProduct->products()->where('shop_id', $product->shop->id)->first();
            if ($leader && $leader->id == $product->id) {
                $product->update([
                    'is_main'           => true,
                    'is_minion_variant' => false,
                    'is_variant_leader' => true,
                ]);
            }
        }

        return $product;
    }


    /**
     * @throws \Throwable
     */
    public function updateFoundProduct(Product $product, array $modelData, bool $createWebpage): void
    {
        $product = UpdateProduct::run($product, $modelData);
        CloneProductImagesFromTradeUnits::run($product);
        $product->refresh();
        if ($product->masterProduct) {
            $this->setVariantData($product, $product->masterProduct);
        }
        if ($createWebpage && $product->webpage === null) {
            $webpage = StoreProductWebpage::run($product);
            PublishWebpage::make()->action($webpage, [
                'comment' => 'first publish'
            ]);
        }
        TranslateModel::dispatch(
            $product,
            Arr::only($modelData, ['name', 'description', 'description_title', 'description_extra'])
        );
    }

    public function rules(): array
    {
        return [
            'shop_products'     => ['sometimes', 'array'],
            'is_minion_variant' => ['sometimes', 'boolean'],
            'is_for_sale'       => ['sometimes', 'boolean']
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, array $modelData, int $hydratorsDelay = 0, $strict = true, $audit = true, $generateVariant = true, $ignoreCreateWebpage = false): void
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterAsset->group;

        $this->initialisationFromGroup($group, $modelData);

        $this->handle($masterAsset, $this->validatedData, $generateVariant, $ignoreCreateWebpage);
    }

}
