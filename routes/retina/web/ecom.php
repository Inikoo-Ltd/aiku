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
use App\Actions\Retina\Ecom\BackInStock\UI\IndexRetinaEcomBackInStocks;
use App\Actions\Retina\Ecom\Basket\UI\ShowRetinaEcomBasket;
use App\Actions\Retina\Ecom\Checkout\UI\ShowRetinaEcomCheckout;
use App\Actions\Retina\Ecom\Favourite\UI\IndexRetinaEcomFavourites;
use App\Actions\Retina\Ecom\NewArrival\UI\IndexRetinaEcomNewArrivals;
use App\Actions\Retina\Ecom\Orders\EcomPdfProformaInvoice;
use App\Actions\Retina\Ecom\Orders\IndexRetinaEcomOrders;
use App\Actions\Retina\Ecom\Orders\ShowRetinaEcomOrder;
use App\Actions\Retina\Ecom\PreviouslyOrdered\UI\IndexRetinaEcomPreviouslyOrdered;
use Illuminate\Support\Facades\Route;

Route::prefix('basket')->as('basket.')->group(function () {
    Route::get('/', ShowRetinaEcomBasket::class)->name('show');
});
Route::prefix('checkout')->as('checkout.')->group(function () {
    Route::get('/', ShowRetinaEcomCheckout::class)->name('show');
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

Route::prefix('interest')->as('interest.')->group(function () {
    Route::prefix('favourites')->as('favourites.')->group(function () {
        Route::get('/', IndexRetinaEcomFavourites::class)->name('index');
    });
    Route::prefix('previously-ordered')->as('previously_ordered.')->group(function () {
        Route::get('/', IndexRetinaEcomPreviouslyOrdered::class)->name('index');
    });
    Route::prefix('back-in-stocks')->as('back_in_stock.')->group(function () {
        Route::get('/', IndexRetinaEcomBackInStocks::class)->name('index');
    });
    Route::prefix('new-arrivals')->as('new_arrivals.')->group(function () {
        Route::get('/', IndexRetinaEcomNewArrivals::class)->name('index');
    });
});
