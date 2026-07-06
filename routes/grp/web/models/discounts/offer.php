<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 22:28:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\DeleteOffer;
use App\Actions\Discounts\Offer\FinishOffer;
use App\Actions\Discounts\Offer\StoreBogoOffer;
use App\Actions\Discounts\Offer\StoreCustomerOffers;
use App\Actions\Discounts\Offer\StoreGiftsOffers;
use App\Actions\Discounts\Offer\StoreProductCategoryDiscount;
use App\Actions\Discounts\Offer\StoreDiscountShipping;
use App\Actions\Discounts\Offer\StoreShopOffer;
use App\Actions\Discounts\Offer\StoreVoucherOffers;
use App\Actions\Discounts\Offer\VolGr\UpdateVolGrGift;
use Illuminate\Support\Facades\Route;

Route::name('offer.')->prefix('offer/{offer:id}')->group(function () {
    Route::patch('update-vol-gr-gift', UpdateVolGrGift::class)->name('update_vol_gr_gift');
    Route::post('finish', FinishOffer::class)->name('finish');
    Route::post('delete', DeleteOffer::class)->name('delete');
});

Route::post('offers/shop/{shop:id}/category-offer', StoreProductCategoryDiscount::class)->name('category_offer.store');
Route::post('offers/shop/{shop:id}/gift-offer', StoreGiftsOffers::class)->name('gift_offer.store');
Route::post('offers/shop/{shop:id}/shop-offer', StoreShopOffer::class)->name('shop_offer.store');
Route::post('offers/shop/{shop:id}/voucher', StoreVoucherOffers::class)->name('store_voucher');
Route::post('offers/shop/{shop:id}/customer-offer', StoreCustomerOffers::class)->name('store_customer_offer');
Route::post('offers/shop/{shop:id}/shipping-offer', StoreDiscountShipping::class)->name('shipping_offer.store');
Route::post('offers/shop/{shop:id}/bogo-offer', StoreBogoOffer::class)->name('bogo_offer.store');
