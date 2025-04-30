<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/


use App\Actions\Retina\Ecom\Basket\UI\IndexRetinaEcomBaskets;
use App\Actions\Retina\Ecom\Checkout\UI\IndexRetinaEcomCheckouts;
use Illuminate\Support\Facades\Route;

Route::prefix('baskets')->as('baskets.')->group(function () {
    Route::get('/', IndexRetinaEcomBaskets::class)->name('index');
});
Route::prefix('checkouts')->as('checkouts.')->group(function () {
    Route::get('/', IndexRetinaEcomCheckouts::class)->name('index');
});
