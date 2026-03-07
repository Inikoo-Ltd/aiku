<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 22:26:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Discounts\Offer\VolGr\StoreVolGrGift;
use Illuminate\Support\Facades\Route;

Route::name('offer_campaign.')->prefix('offer-campaign/{offerCampaign:id}')->group(function () {
    Route::post('store-free-gift', StoreVolGrGift::class)->name('store_vol_gr_gift');

});
