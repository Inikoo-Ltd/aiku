<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 11:31:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\UI\CreateOffer;
use App\Actions\Discounts\Offer\UI\EditOffer;
use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Discounts\Offer\UI\ShowOffer;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\OfferCampaign\UI\CreateGrAmnesty;
use App\Actions\Discounts\OfferCampaign\UI\CreateVolGrGift;
use App\Actions\Discounts\OfferCampaign\UI\EditVolGrGift;
use App\Actions\Discounts\OfferCampaign\UI\IndexOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\UI\ShowOfferCampaign;
use App\Actions\Discounts\UI\ShowDiscountsDashboard;
use App\Stubs\UIDummies\EditDummy;
use Illuminate\Support\Facades\Route;
use App\Actions\Discounts\OfferCampaign\StoreDiscountShipping;
use App\Actions\Discounts\OfferCampaign\StoreCustomerOffers;
use App\Actions\Discounts\OfferCampaign\StoreGiftsOffers;
use App\Actions\Discounts\OfferCampaign\StoreVoucherOffers;
use App\Actions\Discounts\OfferCampaign\StoreCategoryOffers;
use App\Actions\Discounts\OfferCampaign\StoreProductOffers;
use App\Actions\Discounts\OfferCampaign\UI\IndexOrdersInOffer;
use App\Actions\Discounts\OfferCampaign\UI\IndexCustomersInOffer;
use App\Actions\Discounts\OfferCampaign\UI\IndexInvoicesInOffer;
use App\Actions\Discounts\OfferCampaign\UI\IndexOrdersInOfferTotal;
use App\Actions\Discounts\OfferCampaign\UI\IndexCustomersInOfferTotal;
use App\Actions\Discounts\OfferCampaign\UI\IndexInvoicesInOfferTotal;

Route::get('', ShowDiscountsDashboard::class)->name('dashboard');
Route::name("campaigns.")->prefix('campaigns')
    ->group(function () {
        Route::get('', IndexOfferCampaigns::class)->name('index');
        Route::get('{offerCampaign}', ShowOfferCampaign::class)->name('show');
        Route::get('{offerCampaign}/orders', IndexOrdersInOffer::class)->name('orders');
        Route::get('{offerCampaign}/customers', IndexCustomersInOffer::class)->name('customers');
        Route::get('{offerCampaign}/invoices', IndexInvoicesInOffer::class)->name('invoices');
        Route::get('{offerCampaign}/totals/customers', IndexCustomersInOfferTotal::class)->name('totals.customers');
        Route::get('{offerCampaign}/totals/orders', IndexOrdersInOfferTotal::class)->name('totals.orders');
        Route::get('{offerCampaign}/totals/invoices', IndexInvoicesInOfferTotal::class)->name('totals.invoices');

        Route::name('offer.')->prefix('{offerCampaign}/offer')
            ->group(function () {
                Route::get('{offer}', [ShowOffer::class, 'inOfferCampaign'])->name('show');
                Route::get('{offer}/edit', [EditOffer::class, 'inOfferCampaign'])->name('edit');
                Route::get('{offer}/edit-vol-gr-gift', [EditVolGrGift::class, 'inOffer'])->name('edit_vol_gr_gift');
            });

        Route::name('gift.')->prefix('{offerCampaign}/gift')
            ->group(function () {
                Route::get('{offer}', [ShowOffer::class, 'inGiftCampaign'])->name('show');
                Route::get('{offer}/edit', [EditOffer::class, 'inGiftCampaign'])->name('edit');
            });

        Route::name('amnesty.')->prefix('{offerCampaign}/amnesty')
            ->group(function () {
                Route::get('{offer}', [ShowOffer::class, 'inAmnestyCampaign'])->name('show');
                Route::get('{offer}/edit', [EditOffer::class, 'inAmnestyCampaign'])->name('edit');
            });

        Route::get('{offerCampaign}/edit', EditDummy::class)->name('edit');
        Route::get('{offerCampaign}/create-vol-gr-gift', CreateVolGrGift::class)->name('create_vol_gr_gift');
        Route::get('{offerCampaign}/edit-vol-gr-gift', EditVolGrGift::class)->name('edit_vol_gr_gift')->withoutScopedBindings();
        Route::get('{offerCampaign}/create-gr-amnesty-offer', CreateGrAmnesty::class)->name('create_gr_amnesty_offer');

        //todo
        Route::get('{offerCampaign}/edit-gr-amnesty', EditVolGrGift::class)->name('edit_current_gr_amnesty_offer')->withoutScopedBindings();

        Route::post(
            '{offerCampaign}/voucher',
            StoreVoucherOffers::class
        )->name('store_voucher');

        Route::post(
            '{offerCampaign}/shipping',
            StoreDiscountShipping::class
        )->name('store_shipping');

        Route::post(
            '{offerCampaign}/customer',
            StoreCustomerOffers::class
        )->name('store_customer');

        Route::post(
            '{offerCampaign}/gift',
            StoreGiftsOffers::class
        )->name('store_gift');

        Route::post(
            '{offerCampaign}/category',
            StoreCategoryOffers::class
        )->name('store_category');

        Route::post(
            '{offerCampaign}/product',
            StoreProductOffers::class
        )->name('store_product');
    });

Route::name("offers.")->prefix('offers')
    ->group(function () {
        Route::get('', IndexOffers::class)->name('index');
        Route::get('create', CreateOffer::class)->name('create');
        Route::get('{offer}', ShowOffer::class)->name('show');
        Route::get('{offer}/edit', EditOffer::class)->name('edit');
        Route::post('store', StoreOffer::class)->name('store');
        Route::patch('{offer}/update', [UpdateOffer::class, 'inShop'])->name('update');
    });
