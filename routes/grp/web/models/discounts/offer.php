<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 22:28:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\VolGr\UpdateVolGrGift;
use App\Actions\Discounts\OfferCampaign\StoreGiftsOffers;
use Illuminate\Support\Facades\Route;

Route::name('offer.')->prefix('offer/{offer:id}')->group(function () {
    Route::patch('update-vol-gr-gift', UpdateVolGrGift::class)->name('update_vol_gr_gift');
});

Route::post('offers/shop/{shop:id}/category-offer', StoreProductCategoryDiscount::class)->name('category_offer.store');
Route::post('offers/shop/{shop:id}/gift-offer', StoreGiftsOffers::class)->name('gift_offer.store');
