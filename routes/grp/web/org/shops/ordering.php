<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 03:24:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\UI\ShowOrdersBacklog;

Route::get('/backlog', ShowOrdersBacklog::class)->name('backlog');

Route::get('/orders/', IndexOrders::class)->name('orders.index');

Route::prefix('orders/{order}')->group(function () {
    Route::get('', ShowOrder::class)->name('orders.show');
    Route::get('delivery-note/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderInShop'])->name('orders.show.delivery-note');
});

/*


Route::get('/orders/{order}/delivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'inOrder'])->name('orders.show.delivery-notes.show');
Route::get('/orders/{order}/payments/{payment}', [ShowPayment::class,'inOrder'])->name('orders.show.orders.show.payments.show');
Route::get('/orders/{order}/payments/{payment}/edit', [EditPayment::class, 'inOrder'])->name('orders.show.orders.show.payments.edit');

Route::get('/delivery-notes/', [IndexDeliveryNotes::class, 'inOrganisation'])->name('delivery-notes.index');
Route::get('/delivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'inOrganisation'])->name('delivery-notes.show');

Route::get('/invoices/', [IndexInvoices::class, 'inOrganisation'])->name('invoices.index');
Route::get('/invoices/{invoice}', [ShowInvoice::class, 'inOrganisation'])->name('invoices.show');

Route::get('/shops/{shop}', [ShowOrdersBacklog::class,'inShop'])->name('shops.show.dashboard');
Route::get('/shops/{shop}/orders/', [IndexOrders::class, 'InShop'])->name('shops.show.orders.index');
Route::get('/shops/{shop}/orders/{order}', [ShowOrder::class, 'InShop'])->name('shops.show.orders.show');
Route::get('/shops/{shop}/orders/{order}/delivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'InOrderInShop'])->name('shops.show.orders.show.delivery-notes.show');
Route::get('/shops/{shop}/orders/{order}/payments/{payment}', [ShowPayment::class,'InOrderInShop'])->name('shops.show.orders.show.orders.show.payments.show');
Route::get('/shops/{shop}/orders/{order}/payments/{payment}/edit', [EditPayment::class, 'InOrderInShop'])->name('shops.show.orders.show.orders.show.payments.edit');

Route::get('/shops/{shop}/delivery-notes/', [IndexDeliveryNotes::class, 'InShop'])->name('shops.show.delivery-notes.index');
Route::get('/shops/{shop}/delivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'InShop'])->name('shops.show.delivery-notes.show');

Route::get('/shops/{shop}/invoices/', [IndexInvoices::class, 'InShop'])->name('shops.show.invoices.index');
Route::get('/shops/{shop}/invoices/{invoice}', [ShowInvoice::class, 'InShop'])->name('shops.show.invoices.show');
*/
