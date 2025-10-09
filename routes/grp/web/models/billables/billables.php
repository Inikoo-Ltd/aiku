<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use Illuminate\Support\Facades\Route;

Route::name('billables.')->prefix('shop/{shop:id}/billables')->group(function () {
    Route::name('charges.')->prefix('charges')->group(function () {
        Route::post('store', StoreCharge::class)->name('store');
    });
    Route::name('shipping-zone-schemas.')->prefix('shipping-one-schemas')->group(function () {
        Route::post('store', StoreShippingZoneSchema::class)->name('store');
    });
});
