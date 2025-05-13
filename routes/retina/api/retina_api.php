<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\Api\GetProfile;
use App\Actions\Retina\Api\Order\StoreApiOrder;
use App\Actions\Retina\Api\Order\UpdateApiOrder;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'ability:retina'])->group(function () {
    Route::get('/profile', GetProfile::class)->name('profile');

    Route::prefix('order')->as('order.')->group(function () {
        Route::post('{customer:id}/store', StoreApiOrder::class)->name('store');
        Route::patch('{order:id}/update', UpdateApiOrder::class)->name('update');
    });
});
