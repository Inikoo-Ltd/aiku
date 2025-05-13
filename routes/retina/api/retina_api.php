<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 15:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\Api\ShowProfile;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return 'pong';
})->name('ping');
Route::middleware(['auth:sanctum', 'ability:retina'])->group(function () {
    Route::get('/profile', ShowProfile::class)->name('profile');
});
