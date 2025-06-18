<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\GetProfile;
use App\Actions\Api\Order\ShowApiOrder;
use App\Actions\Api\Order\IndexApiOrders;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'set.treblle.authorize', 'treblle'])->group(function () {
    Route::get('/profile', GetProfile::class)->name('profile');


    Route::prefix('order')->as('order.')->group(function () {
        Route::get('', IndexApiOrders::class)->name('index');
        Route::get('{order:id}', ShowApiOrder::class)->name('show');
    });
});
