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
use App\Models\Discounts\Offer;

trait HandlesOfferSideEffects
{
    protected function handleOfferSideEffects(Offer $offer, bool $statusChanged): void
    {
        if ($offer->offerCampaign) {
            OfferCampaignHydrateOffersState::run($offer->offerCampaign);
        } else {
            ShopHydrateOffersData::run($offer->shop_id);
        }

        if ($statusChanged) {
            if ($offer->voucher) {
                CleanFinishedVouchers::run($offer->id);
            }

            if ($offer->trigger_type == 'ProductCategory') {
                UpdateProductCategoryOffersData::run($offer);
            }

            if ($offer->customer_id) {
                RecalculateCustomerTotalsOrdersInBasket::dispatch($offer->customer_id)->delay(now()->addSeconds(10));
            } else {
                RecalculateShopOrderDiscountsInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));
            }
        }
    }
}
