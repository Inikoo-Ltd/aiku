<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Traits\HasPriceMetrics;
use App\Models\Catalogue\Product;
use Illuminate\Support\Arr;

trait WithIrisSearchEnrichedItems
{
    use HasPriceMetrics;

    /**
     * Attach the storefront canonical url and an image to each search hit.
     * Products use a larger 150x150 image; categories and collections keep the small thumbnail.
     * Hits are filtered by is_in_website (live webpage + sellable) as a backstop for a
     * stale Typesense index: the shared index also holds items not published on the
     * storefront, which must not leak to the public.
     *
     * @param array<int, array<string, mixed>> $items
     * @param class-string $modelClass
     *
     * @return array<int, array<string, mixed>>
     */
    protected function enrichItems(array $items, string $modelClass, bool $largeImage = false, bool $withOffers = false): array
    {
        $ids = array_filter(array_column($items, 'id'));
        if (empty($ids)) {
            return [];
        }

        $models = $modelClass::query()
            ->whereIn('id', $ids)
            ->with(['webpage' => fn ($query) => $query->where('website_id', $this->website->id)->where('state', WebpageStateEnum::LIVE)->with('shop')])
            ->get()
            ->keyBy('id');

        $showPrice = auth()->check();

        $enriched = [];
        foreach ($items as $item) {
            $model = $models->get($item['id']);
            $url   = $model?->webpage?->getCanonicalUrl();
            if (!$url) {
                continue;
            }

            if (!$model->is_in_website) {
                continue;
            }

            $image = $largeImage
                ? $model->imageSources(150, 150)
                : Arr::get($model->web_images ?? [], 'main.thumbnail');

            $item['url']   = $url;
            $item['image'] = $image ?: $item['image'] ?? null;
            $item['stock'] = $model->available_quantity;
            $item['units'] = $model->units;
            $item['unit']  = $model->unit;

            if ($model instanceof Product) {
                $item['is_golden_product'] = (bool)$model->is_golden_product;
            }

            if ($showPrice) {
                $item['price'] = $model->price;

                if ($withOffers && $model instanceof Product) {
                    $item = array_merge($item, $this->getProductOfferPricing($model));
                }
            }

            $enriched[] = $item;
        }

        return $enriched;
    }

    /**
     * Retail price and the offers a customer can already claim on the product, so a hit can be
     * shown with its discounted price instead of the plain one.
     *
     * @return array<string, mixed>
     */
    private function getProductOfferPricing(Product $product): array
    {
        $offersData        = $product->offers_data ?: [];
        $bestPercentageOff = (float)Arr::get($offersData, 'best_percentage_off.percentage_off', 0);
        $price             = (float)$product->price;

        [, $rrpPerUnit, , , , $pricePerUnit]  = $this->getPriceMetrics($product->rrp, $price, $product->units);
        [, , , , , $discountedPricePerUnit]   = $this->getPriceMetrics($product->rrp, (1 - $bestPercentageOff) * $price, $product->units);

        return [
            'family_id'                 => $product->family_id,
            'rrp'                       => $product->rrp,
            'rrp_per_unit'              => $rrpPerUnit,
            'price_per_unit'            => $pricePerUnit,
            'product_offers_data'       => $offersData,
            'discounted_price'          => $bestPercentageOff ? round($price * (1 - $bestPercentageOff), 2) : null,
            'discounted_price_per_unit' => $bestPercentageOff ? $discountedPricePerUnit : null,
            'discounted_percentage'     => $bestPercentageOff ? percentage($bestPercentageOff, 1) : null,
        ];
    }
}
