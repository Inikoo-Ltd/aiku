<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jun 2026 12:08:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\Traits;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffersState;
use App\Actions\Ordering\Order\CleanFinishedVouchers;
use App\Actions\Ordering\Order\RecalculateCustomerTotalsOrdersInBasket;
use App\Actions\Ordering\Order\RecalculateShopOrderDiscountsInBasket;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;

trait HandlesOfferSideEffects
{
    protected function handleOfferSideEffects(Offer $offer, $recalculateBasket = true): void
    {
        if ($offer->offerCampaign) {
            OfferCampaignHydrateOffersState::run($offer->offerCampaign);
        }

        ShopHydrateOffersData::run($offer->shop_id);


        if ($offer->voucher) {
            CleanFinishedVouchers::run($offer->id);
        }

        if ($offer->trigger_type == 'ProductCategory') {
            UpdateProductCategoryOffersData::run($offer);
        }

        $this->cleanWebpagesCache($offer);


        if ($recalculateBasket) {
            if ($offer->customer_id) {
                RecalculateCustomerTotalsOrdersInBasket::dispatch($offer->customer_id)->delay(now()->addSeconds(10));
            } else {
                RecalculateShopOrderDiscountsInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));
            }
        }
    }

    public function cleanWebpagesCache(Offer $offer): void
    {
        if ($offer->trigger_type == 'ProductCategory') {
            /** @var ProductCategory $productCategory */
            $productCategory = $offer->trigger;

            if ($productCategory && $productCategory->webpage) {
                BreakWebpageCache::run($productCategory->webpage, true);
            }
        } elseif ($offer->trigger_type == 'Product') {
            /** @var Product $product */
            $product = $offer->trigger;

            if ($product && $product->webpage) {
                BreakWebpageCache::run($product->webpage);
            }
        }
    }


}
