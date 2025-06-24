<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Api\Retina\GetProfile;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');

Route::middleware(['auth:sanctum', 'ability:retina', 'set.treblle.authorize', 'treblle'])->group(function () {
    Route::get('/profile', GetProfile::class)->name('profile');

    Route::prefix("dropshipping")
        ->name("dropshipping.")
        ->group(__DIR__."/dropshipping/dropshipping.php");

    Route::prefix("fulfilment")
        ->name("fulfilment.")
        ->group(__DIR__."/fulfilment/fulfilment.php");


});
