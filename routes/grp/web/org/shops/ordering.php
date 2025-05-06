<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 03:24:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotesInOrdering;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Ordering\Order\DownloadOrderTransactionsTemplate;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Purge\UI\CreatePurge;
use App\Actions\Ordering\Purge\UI\EditPurge;
use App\Actions\Ordering\Purge\UI\IndexPurges;
use App\Actions\Ordering\Purge\UI\ShowPurge;
use App\Actions\Ordering\UI\ShowOrderingDashboard;
use App\Actions\Ordering\UI\ShowOrdersBacklog;
use Illuminate\Support\Facades\Route;

Route::get('', ShowOrderingDashboard::class)->name('dashboard');


Route::get('/backlog', ShowOrdersBacklog::class)->name('backlog');

Route::get('/orders/', IndexOrders::class)->name('orders.index');




Route::get('/orders/delivery_notes', IndexDeliveryNotesInOrdering::class)->name('delivery-notes.index');
Route::get('/orders/delivery_notes/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderingInShop'])->name('delivery-notes.show');

Route::prefix('orders/{order}')->group(function () {
    Route::get('', ShowOrder::class)->name('orders.show');
    Route::get('delivery-note/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderInShop'])->name('orders.show.delivery-note');
    Route::get('order-transaction-templates', DownloadOrderTransactionsTemplate::class)->name('order.uploads.templates');
});

Route::get('/purges/', IndexPurges::class)->name('purges.index');
Route::get('/purges/create', CreatePurge::class)->name('purges.create');

Route::prefix('purges/{purge:id}')->group(function () {
    Route::get('', ShowPurge::class)->name('purges.show');
    Route::get('edit', EditPurge::class)->name('purges.edit');
    Route::get('order/{order}', [ShowOrder::class, 'inPurge'])->name('purges.order')->withoutScopedBindings();
});
