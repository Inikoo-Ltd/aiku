<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 21:32:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\UI\Incoming\ShowAgentIncomingHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAgentIncomingHub::class)->name('backlog');
Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inWarehouse'])->name('stock_deliveries.index');
Route::get('stock-deliveries/{palletDelivery}', [ShowStockDelivery::class, 'inWarehouse'])->name('stock_deliveries.show');
