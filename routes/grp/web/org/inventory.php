<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 14:22:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\OrgStock\ExportStocks;
use App\Actions\Inventory\OrgStock\UI\CreateStock;
use App\Actions\Inventory\OrgStock\UI\EditStock;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Inventory\OrgStock\UI\RemoveStock;
use App\Actions\Inventory\OrgStock\UI\ShowStock;
use App\Actions\SupplyChain\StockFamily\ExportStockFamilies;
use App\Actions\SupplyChain\StockFamily\UI\CreateStockFamily;
use App\Actions\SupplyChain\StockFamily\UI\EditStockFamily;
use App\Actions\SupplyChain\StockFamily\UI\IndexStockFamilies;
use App\Actions\SupplyChain\StockFamily\UI\RemoveStockFamily;
use App\Actions\SupplyChain\StockFamily\UI\ShowStockFamily;
use App\Actions\UI\Inventory\ShowInventoryDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowInventoryDashboard::class)->name('dashboard');


Route::get('/families/export', ExportStockFamilies::class)->name('stock-families.export');

Route::get('/families', IndexStockFamilies::class)->name('stock-families.index');
Route::get('/families/create', CreateStockFamily::class)->name('stock-families.create');
Route::get('/families/{stockFamily}', ShowStockFamily::class)->name('stock-families.show');
Route::get('/families/{stockFamily}/edit', EditStockFamily::class)->name('stock-families.edit');
Route::get('/families/{stockFamily}/delete', RemoveStockFamily::class)->name('stock-families.remove');
Route::get('/families/{stockFamily}/stocks', [IndexOrgStocks::class, 'inStockFamily'])->name('stock-families.show.stocks.index');
Route::get('/families/{stockFamily}/stocks/create', [CreateStock::class,'inStockFamily'])->name('stock-families.show.stocks.create');
Route::get('/families/{stockFamily}/stocks/{stock}', [ShowStock::class, 'inStockFamily'])->name('stock-families.show.stocks.show');
Route::get('/families/{stockFamily}/stocks/{stock}/edit', [EditStock::class, 'inStockFamily'])->name('stock-families.show.stocks.edit');
Route::get('/families/{stockFamily}/stocks/{stock}/delete', [RemoveStock::class, 'inStockFamily'])->name('stock-families.show.stocks.remove');

Route::get('/stocks/export', ExportStocks::class)->name('stocks.export');

Route::get('/stocks', IndexOrgStocks::class)->name('stocks.index');
Route::get('/stocks/create', CreateStock::class)->name('stocks.create');
Route::get('/stocks/{stock}', ShowStock::class)->name('stocks.show');
Route::get('/stocks/{stock}/edit', EditStock::class)->name('stocks.edit');
