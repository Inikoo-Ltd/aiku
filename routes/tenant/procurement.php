<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\Procurement\Agent\UI\CreateAgent;
use App\Actions\Procurement\Agent\UI\EditAgent;
use App\Actions\Procurement\Agent\UI\IndexAgents;
use App\Actions\Procurement\Agent\UI\ShowAgent;
use App\Actions\Procurement\Supplier\UI\CreateSupplier;
use App\Actions\Procurement\Supplier\UI\EditSupplier;
use App\Actions\Procurement\Supplier\UI\IndexSuppliers;
use App\Actions\Procurement\Supplier\UI\ShowSupplier;
use App\Actions\Procurement\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\Procurement\SupplierProduct\UI\ShowSupplierProduct;
use App\Actions\UI\Procurement\ProcurementDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ProcurementDashboard::class)->name('dashboard');
Route::get('/suppliers', IndexSuppliers::class)->name('suppliers.index');
Route::get('/suppliers/create', CreateSupplier::class)->name('suppliers.create');
Route::get('/suppliers/{supplier}', ShowSupplier::class)->name('suppliers.show');
Route::get('/suppliers/{supplier}/edit', EditSupplier::class)->name('suppliers.edit');

Route::get('/agents', IndexAgents::class)->name('agents.index');
Route::get('/agents/create', CreateAgent::class)->name('agents.create');
Route::get('/agents/{agent}', ShowAgent::class)->name('agents.show');
Route::get('/agents/{agent}/edit', EditAgent::class)->name('agents.edit');

Route::get('/agents/{agent}/suppliers', [IndexSuppliers::class, 'inAgent'])->name('agents.show.suppliers.index');
Route::get('/agents/{agent}/suppliers/{supplier}', [ShowSupplier::class, 'inAgent'])->name('agents.show.suppliers.show');

Route::get('/supplier-products', IndexSupplierProducts::class)->name('supplier-products.index');
Route::get('/supplier-products/{supplierProduct}', ShowSupplierProduct::class)->name('supplier-products.show');

//Route::get('/purchase-orders', IndexPurchaseOrders::class)->name('purchase-orders.index');
//Route::get('/purchase-orders/{purchaseOrder}', ShowPurchaseOrder::class)->name('purchase-orders.show');
