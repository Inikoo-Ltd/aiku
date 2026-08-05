<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026 09:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait WithFaireProductPayload
{
    protected int $faireDescriptionLimit = 997;
    protected int $faireShortDescriptionLimit = 72;

    public function isFaireSyncableProduct(Product $product): bool
    {
        $shop = $product->shop;

        return $shop
            && $shop->type === ShopTypeEnum::EXTERNAL
            && $shop->engine === ShopEngineEnum::FAIRE
            && Arr::has($shop->settings, 'faire.access_token');
    }

    /**
     * Null leaves the Faire lifecycle state untouched: a product with no image cannot be
     * published on Faire, and pushing a state we cannot satisfy would unpublish live products.
     */
    public function getFaireLifecycleState(Product $product): ?string
    {
        if (!$product->is_for_sale || in_array($product->state, [ProductStateEnum::IN_PROCESS, ProductStateEnum::DISCONTINUED])) {
            return 'UNPUBLISHED';
        }

        return $this->getFaireProductImages($product) ? 'PUBLISHED' : null;
    }

    public function getCreateFaireLifecycleState(Product $product): string
    {
        if (!$this->getFaireProductImages($product)) {
            return 'DRAFT';
        }

        return $this->getFaireLifecycleState($product) ?? 'DRAFT';
    }

    public function getFaireProductImages(Product $product): array
    {
        $url = Arr::get($product->imageSources() ?? [], 'original');

        return $url ? [['url' => $url]] : [];
    }

    public function getFaireUnitMultiplier(Product $product): int
    {
        return max(1, (int)round($product->units));
    }

    /**
     * Faire holds a wholesale price per unit while Aiku holds the price of the whole outer,
     * which is how GetFaireProducts reads them back in.
     *
     * @return array<int, array{geo_constraint: array, wholesale_price: array, retail_price: array}>
     */
    public function getFaireVariantPrices(Product $product): array
    {
        $shop     = $product->shop;
        $currency = $shop->currency->code;
        $units    = $product->units > 0 ? $product->units : 1;

        $wholesalePrice = [
            'amount_minor' => (int)round($product->price / $units * 100),
            'currency'     => $currency,
        ];

        $retailPrice = [
            'amount_minor' => (int)round($product->rrp * 100),
            'currency'     => $currency,
        ];

        $prices   = [];
        $repriced = false;

        foreach (Arr::get($product->data, 'faire.prices', []) as $fairePrice) {
            if (Arr::get($fairePrice, 'wholesale_price.currency') === $currency) {
                $prices[] = [
                    ...$fairePrice,
                    'wholesale_price' => $wholesalePrice,
                    'retail_price'    => $retailPrice,
                ];
                $repriced = true;

                continue;
            }

            $prices[] = $fairePrice;
        }

        if (!$repriced) {
            $prices[] = [
                'geo_constraint'  => ['country' => $shop->country->iso3],
                'wholesale_price' => $wholesalePrice,
                'retail_price'    => $retailPrice,
            ];
        }

        return $prices;
    }

    public function getFaireProductPayload(Product $product): array
    {
        $payload = [
            'name'                   => $product->name,
            'unit_multiplier'        => $this->getFaireUnitMultiplier($product),
            'minimum_order_quantity' => $this->getFaireUnitMultiplier($product),
        ];

        if ($product->description) {
            data_set($payload, 'description', Str::limit(strip_tags($product->description), $this->faireDescriptionLimit));
        }

        if ($product->description_title) {
            data_set($payload, 'short_description', Str::limit(strip_tags($product->description_title), $this->faireShortDescriptionLimit));
        }

        return $payload;
    }

    public function getFaireVariantPayload(Product $product): array
    {
        $payload = [
            'sku'    => $product->code,
            'prices' => $this->getFaireVariantPrices($product),
        ];

        if ($product->barcode) {
            data_set($payload, 'gtin', $product->barcode);
        }

        if ($lifecycleState = $this->getFaireLifecycleState($product)) {
            data_set($payload, 'lifecycle_state', $lifecycleState);
        }

        return $payload;
    }

    /**
     * A Faire product can hold several variants, each of them a product of its own in Aiku.
     * The product level fields are shared by all of them and must not be written from a single sibling.
     */
    public function hasSiblingsInFaireProduct(Product $product, string $faireProductId): bool
    {
        return Product::where('shop_id', $product->shop_id)
            ->where('marketplace_second_id', $faireProductId)
            ->where('id', '!=', $product->id)
            ->exists();
    }

    public function getCreateFaireProductPayload(Product $product): array
    {
        $payload = [
            ...$this->getFaireProductPayload($product),
            'idempotence_token'   => 'aiku-product-'.$product->id,
            'lifecycle_state'     => $this->getCreateFaireLifecycleState($product),
            'variant_option_sets' => [],
            'variants'            => [
                [
                    ...$this->getFaireVariantPayload($product),
                    'name'              => $product->name,
                    'lifecycle_state'   => $this->getCreateFaireLifecycleState($product),
                    'idempotence_token' => 'aiku-variant-'.$product->id,
                ]
            ],
        ];

        if ($images = $this->getFaireProductImages($product)) {
            data_set($payload, 'images', $images);
            data_set($payload, 'variants.0.images', $images);
        }

        return $payload;
    }

    /**
     * Images are deliberately left out: Faire appends the images of a PATCH to the ones it
     * already holds, so resending them on every update would pile up duplicates.
     */
    public function getUpdateFaireProductPayload(Product $product): array
    {
        $payload = [
            ...$this->getFaireProductPayload($product),
            'variants' => [
                [
                    'id'   => $product->marketplace_id,
                    'name' => $product->name,
                    ...$this->getFaireVariantPayload($product),
                ]
            ],
        ];

        if ($lifecycleState = $this->getFaireLifecycleState($product)) {
            data_set($payload, 'lifecycle_state', $lifecycleState);
        }

        return $payload;
    }
}
