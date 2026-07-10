<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Leaflet\DeleteLeaflet;
use App\Actions\Billables\Leaflet\StoreLeaflet;
use App\Actions\Billables\Leaflet\UpdateLeaflet;
use App\Actions\Billables\Packaging\DeletePackaging;
use App\Actions\Billables\Packaging\StorePackaging;
use App\Actions\Billables\Packaging\StorePackagingFamily;
use App\Actions\Billables\Packaging\UpdatePackaging;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use Illuminate\Support\Facades\Route;

Route::name('billables.')->prefix('shop/{shop:id}/billables')->group(function () {
    Route::name('charges.')->prefix('charges')->group(function () {
        Route::post('store', StoreCharge::class)->name('store');
    });
    Route::name('packagings.')->prefix('packagings')->group(function () {
        Route::post('store', StorePackaging::class)->name('store');
        Route::post('family/store', StorePackagingFamily::class)->name('family.store');
    });
    Route::name('leaflets.')->prefix('leaflets')->group(function () {
        Route::post('store', StoreLeaflet::class)->name('store');
    });
    Route::name('shipping-zone-schemas.')->prefix('shipping-one-schemas')->group(function () {
        Route::post('store', StoreShippingZoneSchema::class)->name('store');
    });
});

Route::name('billables.packagings.')->prefix('packaging/{packaging:id}')->group(function () {
    Route::patch('update', UpdatePackaging::class)->name('update');
    Route::delete('', DeletePackaging::class)->name('delete');
});

Route::name('billables.leaflets.')->prefix('leaflet/{leaflet:id}')->group(function () {
    Route::patch('update', UpdateLeaflet::class)->name('update');
    Route::delete('', DeleteLeaflet::class)->name('delete');
});
