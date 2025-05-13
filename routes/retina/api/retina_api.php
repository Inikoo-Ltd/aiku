<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\Api\GetProfile;
use App\Actions\Retina\Api\Order\GetOrder;
use App\Actions\Retina\Api\Order\GetOrders;
use App\Actions\Retina\Api\Order\StoreApiOrder;
use App\Actions\Retina\Api\Order\SubmitApiOrder;
use App\Actions\Retina\Api\Order\UpdateApiOrder;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'ability:retina'])->group(function () {
    Route::get('/profile', GetProfile::class)->name('profile');


    Route::prefix('order')->as('order.')->group(function () {
        Route::get('', GetOrders::class)->name('index');
        Route::get('{order:id}', GetOrder::class)->name('show');
        Route::post('/store', StoreApiOrder::class)->name('store');
        Route::patch('{order:id}/update', UpdateApiOrder::class)->name('update');
        Route::patch('{order:id}/submit', SubmitApiOrder::class)->name('submit');
    });

});
