<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/


use App\Actions\Retina\Billing\RetinaPdfInvoice;
use App\Actions\Retina\Billing\UI\IndexRetinaEcomInvoices;
use App\Actions\Retina\Billing\UI\ShowRetinaEcomInvoice;
use App\Actions\Retina\Ecom\Basket\FetchRetinaEcomBasket;
use App\Actions\Retina\Ecom\BackInStock\UI\IndexRetinaEcomBackInStocks;
use App\Actions\Retina\Ecom\Basket\UI\ShowRetinaEcomBasket;
use App\Actions\Retina\Ecom\Checkout\UI\ShowRetinaEcomCheckout;
use App\Actions\Retina\Ecom\Favourite\UI\IndexRetinaEcomFavourites;
use App\Actions\Retina\Ecom\Orders\EcomPdfProformaInvoice;
use App\Actions\Retina\Ecom\Orders\IndexRetinaEcomOrders;
use App\Actions\Retina\Ecom\Orders\ShowRetinaEcomOrder;
use Illuminate\Support\Facades\Route;

Route::prefix('basket')->as('basket.')->group(function () {
    Route::get('/', ShowRetinaEcomBasket::class)->name('show');
    Route::post('/fetch', FetchRetinaEcomBasket::class)->name('fetch');
});
Route::prefix('checkout')->as('checkout.')->group(function () {
    Route::get('/', ShowRetinaEcomCheckout::class)->name('show');
});

Route::prefix('favourites')->as('favourites.')->group(function () {
    Route::get('/', IndexRetinaEcomFavourites::class)->name('index');
});

Route::prefix('back-in-stocks')->as('back_in_stock.')->group(function () {
    Route::get('/', IndexRetinaEcomBackInStocks::class)->name('index');
});

Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('', IndexRetinaEcomInvoices::class)->name('index');
    Route::get('{invoice}', ShowRetinaEcomInvoice::class)->name('show');
    Route::get('{invoice}/pdf', RetinaPdfInvoice::class)->name('pdf');
});

Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('', IndexRetinaEcomOrders::class)->name('index');
    Route::get('{order}', ShowRetinaEcomOrder::class)->name('show');
    Route::get('{order}/proforma-invoice', EcomPdfProformaInvoice::class)->name('proforma_invoice.download');
});
