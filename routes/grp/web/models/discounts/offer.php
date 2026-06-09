<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 22:28:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreShopOffer;
use App\Actions\Discounts\Offer\UI\FinishOffer;
use App\Actions\Discounts\Offer\VolGr\UpdateVolGrGift;
use App\Actions\Discounts\OfferCampaign\StoreCustomerOffers;
use App\Actions\Discounts\OfferCampaign\StoreGiftsOffers;
use App\Actions\Discounts\OfferCampaign\StoreVoucherOffers;
use Illuminate\Support\Facades\Route;

Route::name('offer.')->prefix('offer/{offer:id}')->group(function () {
    Route::patch('update-vol-gr-gift', UpdateVolGrGift::class)->name('update_vol_gr_gift');
    Route::get('finish', FinishOffer::class)->name('finish');
});

Route::post('offers/shop/{shop:id}/category-offer', StoreProductCategoryDiscount::class)->name('category_offer.store');
Route::post('offers/shop/{shop:id}/gift-offer', StoreGiftsOffers::class)->name('gift_offer.store');
Route::post('offers/shop/{shop:id}/shop-offer', StoreShopOffer::class)->name('shop_offer.store');
Route::post('offers/shop/{shop:id}/voucher', StoreVoucherOffers::class)->name('store_voucher');
Route::post('offers/shop/{shop:id}/customer-offer', StoreCustomerOffers::class)->name('store_customer_offer');
