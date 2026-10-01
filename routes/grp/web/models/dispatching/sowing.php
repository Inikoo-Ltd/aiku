<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 19 Dec 2024 Malaysia Time
 * Copyright (c) 2024
 */

use App\Actions\GoodsIn\Sowing\DeleteSowing;
use Illuminate\Support\Facades\Route;

Route::name('sowing.')->prefix('sowing/{sowing:id}')->group(function () {
    Route::delete('delete', DeleteSowing::class)->name('delete');
});
