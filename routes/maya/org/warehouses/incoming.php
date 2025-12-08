<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 09:40:59 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\GoodsIn\UI\IndexWarehousePalletDeliveries;
use App\Actions\Inventory\GoodsIn\UI\IndexWarehousePalletsInDelivery;
use App\Actions\Inventory\GoodsIn\UI\ShowWarehousePalletDelivery;
use App\Actions\Procurement\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\StockDelivery\UI\ShowStockDelivery;
use App\Actions\UI\Incoming\ShowIncomingHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowIncomingHub::class)->name('backlog');

Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inWarehouse'])->name('stock_deliveries.index');
Route::get('stock-deliveries/{stockDelivery:id}', [ShowStockDelivery::class, 'inWarehouse'])->name('stock_deliveries.show');

Route::get('handling-fulfilment-deliveries', [IndexWarehousePalletDeliveries::class, 'inWarehouseHandling'])->name('pallet_deliveries.handling.index');
Route::get('booked-in-fulfilment-deliveries', [IndexWarehousePalletDeliveries::class, 'inWarehouseBookedIn'])->name('pallet_deliveries.booked_in.index');

Route::get('fulfilment-deliveries/{palletDelivery:id}', ShowWarehousePalletDelivery::class)->name('pallet_deliveries.show');
Route::get('fulfilment-deliveries/{palletDelivery:id}/pallets', IndexWarehousePalletsInDelivery::class)->name('pallet_deliveries.show.pallets');
