<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jan 2024 11:31:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Discounts\Offer\UI\ShowOffer;
use App\Actions\Discounts\Offer\UI\EditOffer;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Actions\Discounts\OfferCampaign\UI\IndexOfferCampaigns;
use App\Actions\Discounts\OfferCampaign\UI\ShowOfferCampaign;
use App\Actions\Discounts\UI\ShowDiscountsDashboard;
use App\Stubs\UIDummies\CreateDummy;
use App\Stubs\UIDummies\EditDummy;
use Illuminate\Support\Facades\Route;

Route::get('', ShowDiscountsDashboard::class)->name('dashboard');
Route::name("campaigns.")->prefix('campaigns')
    ->group(function () {
        Route::get('', IndexOfferCampaigns::class)->name('index');
        Route::get('{offerCampaign}', ShowOfferCampaign::class)->name('show');
        Route::get('{offerCampaign}/edit', EditDummy::class)->name('edit');
    });

Route::name("offers.")->prefix('offers')
    ->group(function () {
        Route::get('', IndexOffers::class)->name('index');
        Route::get('create', CreateDummy::class)->name('create');
        Route::get('{offer}', ShowOffer::class)->name('show');
        Route::get('{offer}/edit', EditOffer::class)->name('edit');
        Route::patch('{offer}/update', [UpdateOffer::class, 'inShop'])->name('update');
    });
