<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 21:32:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\DeliveryNote\PdfDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Procurement\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\StockDelivery\UI\ShowStockDelivery;
use App\Actions\UI\Dispatch\ShowAgentDispatchHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAgentDispatchHub::class)->name('backlog');
Route::get('/deelivery-notes', IndexDeliveryNotes::class)->name('delivery_notes');
Route::get('/deelivery-notes/unassigned', [IndexDeliveryNotes::class, 'unassigned'])->name('unassigned.delivery_notes');
Route::get('/deelivery-notes/queued', [IndexDeliveryNotes::class, 'queued'])->name('queued.delivery_notes');
Route::get('/deelivery-notes/handling', [IndexDeliveryNotes::class, 'handling'])->name('handling.delivery_notes');
Route::get('/deelivery-notes/handling-blocked', [IndexDeliveryNotes::class, 'handlingBlocked'])->name('handling-blocked.delivery_notes');
Route::get('/deelivery-notes/packed', [IndexDeliveryNotes::class, 'packed'])->name('packed.delivery_notes');
Route::get('/deelivery-notes/finalised', [IndexDeliveryNotes::class, 'finalised'])->name('finalised.delivery_notes');
Route::get('/deelivery-notes/dispatched', [IndexDeliveryNotes::class, 'dispatched'])->name('dispatched.delivery_notes');
Route::get('/deelivery-notes/{deliveryNote}', [ShowDeliveryNote::class, 'inWarehouse'])->name('delivery_notes.show');
Route::get('/deelivery-notes/{deliveryNote}/pdf', PdfDeliveryNote::class)->name('delivery_notes.pdf');

Route::prefix('stock-deliveries')->as('stock_deliveries.')->group(function () {
    Route::get('', [IndexStockDeliveries::class, 'inWarehouse'])->name('index');
    Route::prefix('/{stockDelivery}')->as('show')->group(function () {
        Route::get('', [ShowStockDelivery::class, 'inWarehouse'])->name('');
    });
});