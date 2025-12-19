<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

use App\Actions\Dispatching\Sowing\AssignSowerToSowing;
use App\Actions\Dispatching\Sowing\DeleteSowing;
use App\Actions\Dispatching\Sowing\StoreSowing;
use App\Actions\Dispatching\Sowing\UpdateSowing;
use Illuminate\Support\Facades\Route;

Route::name('sowing.')->prefix('sowing/{sowing:id}')->group(function () {
    Route::patch('update', UpdateSowing::class)->name('update');
    Route::delete('delete', DeleteSowing::class)->name('delete');

    Route::name('assign.')->prefix('assign')->group(function () {
        Route::patch('sower', AssignSowerToSowing::class)->name('sower');
    });
});
