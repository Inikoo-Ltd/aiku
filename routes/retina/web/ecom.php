<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/


use App\Actions\Retina\Ecom\Basket\UI\ShowRetinaEcomBasket;
use App\Actions\Retina\Ecom\Checkout\UI\ShowRetinaEcomCheckout;
use App\Actions\Retina\Ecom\Favourite\UI\IndexRetinaEcomFavourites;
use Illuminate\Support\Facades\Route;

Route::prefix('basket')->as('basket.')->group(function () {
    Route::get('/', ShowRetinaEcomBasket::class)->name('show');
});
Route::prefix('checkout')->as('checkout.')->group(function () {
    Route::get('/', ShowRetinaEcomCheckout::class)->name('show');
});

Route::prefix('favourites')->as('favourites.')->group(function () {
    Route::get('/', IndexRetinaEcomFavourites::class)->name('index');
});
