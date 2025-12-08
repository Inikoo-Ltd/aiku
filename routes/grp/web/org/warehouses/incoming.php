<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 09:40:59 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\GoodsIn\UI\IndexWarehousePalletDeliveries;
use App\Actions\Inventory\GoodsIn\UI\ShowWarehousePalletDelivery;
use App\Actions\Procurement\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\StockDelivery\UI\ShowStockDelivery;
use App\Actions\UI\Incoming\ShowIncomingHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowIncomingHub::class)->name('backlog');

Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inWarehouse'])->name('stock_deliveries.index');
Route::get('stock-deliveries/{palletDelivery}', [ShowStockDelivery::class, 'inWarehouse'])->name('stock_deliveries.show');

Route::get('fulfilment-deliveries', IndexWarehousePalletDeliveries::class)->name('pallet_deliveries.index');
Route::get('fulfilment-deliveries/{palletDelivery}', ShowWarehousePalletDelivery::class)->name('pallet_deliveries.show');
