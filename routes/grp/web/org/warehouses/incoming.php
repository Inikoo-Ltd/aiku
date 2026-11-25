<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 09:40:59 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dispatching\ProductionOutput\PutAwayFinishedJobOrder;
use App\Actions\Fulfilment\UI\GoodsIn\UI\IndexWarehousePalletDeliveries;
use App\Actions\Fulfilment\UI\GoodsIn\UI\ShowWarehousePalletDelivery;
use App\Actions\GoodsIn\Return\UI\IndexReturns;
use App\Actions\GoodsIn\Return\UI\ShowReturn;
use App\Actions\GoodsIn\ReturnDeliveryNote\UI\IndexReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNote\UI\ShowReturnDeliveryNote;
use App\Actions\GoodsIn\StockDelivery\ReceiveSupplierParcel;
use App\Actions\GoodsIn\StockDelivery\UI\GetSupplierParcelPurchaseOrders;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\UI\Incoming\IndexIncomingProductionOutput;
use App\Actions\UI\Incoming\ShowIncomingHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowIncomingHub::class)->name('backlog');
Route::get('/production-output', IndexIncomingProductionOutput::class)->name('production_output.index');
Route::post('/production-output/put-away', PutAwayFinishedJobOrder::class)->name('production_output.put_away')->withoutScopedBindings();

Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inWarehouse'])->name('stock_deliveries.index');
Route::get('stock-deliveries/{stockDelivery}', [ShowStockDelivery::class, 'inWarehouse'])->name('stock_deliveries.show');
Route::get('supplier-parcel', GetSupplierParcelPurchaseOrders::class)->name('supplier_parcel.options');
Route::post('supplier-parcel', ReceiveSupplierParcel::class)->name('supplier_parcel.receive');


Route::get('fulfilment-deliveries', IndexWarehousePalletDeliveries::class)->name('pallet_deliveries.index');
Route::get('fulfilment-deliveries/{palletDelivery}', ShowWarehousePalletDelivery::class)->name('pallet_deliveries.show');

Route::get('returns', IndexReturns::class)->name('returns.index');
Route::get('returns/{return}', ShowReturn::class)->name('returns.show');

Route::name('return_delivery_notes.')->prefix('returns-dn')->group(function () {
    Route::get('/', IndexReturnDeliveryNotes::class)->name('index');

    Route::name('state.')->prefix('/state')->group(function () {
        Route::get('/received', [IndexReturnDeliveryNotes::class, 'received'])->name('received');
        Route::get('/returning', [IndexReturnDeliveryNotes::class, 'returning'])->name('returning');
        Route::get('/returned', [IndexReturnDeliveryNotes::class, 'returned'])->name('returned');
        Route::get('/processed', [IndexReturnDeliveryNotes::class, 'processed'])->name('processed');
    });


    Route::get('/returns-dn/{returnDeliveryNote}', [ShowReturnDeliveryNote::class, 'inWarehouse'])->name('show');
});
