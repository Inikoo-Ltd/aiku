<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

// todo: delete this

/*
Route::get('/agents', IndexMarketplaceAgents::class)->name('agents.index');
Route::get('/agents/{agent}', ShowAgent::class)->name('agents.show')->withTrashed();

Route::get('/agents/{agent}/edit', EditAgent::class)->name('agents.edit');
Route::get('/agents/{agent}/delete', RemoveMarketplaceAgent::class)->name('agents.remove');

Route::get('/agents/{agent}/suppliers', [IndexSuppliers::class, 'inAgent'])->name('agents.show.suppliers.index');
Route::get('/agents/{agent}/suppliers/create', [CreateMarketplaceSupplier::class, 'inAgent'])->name('agents.show.suppliers.create');

Route::get('/agents/{agent}/supplier-products', [IndexMarketplaceSupplierProducts::class, 'inAgent'])->name('agents.show.supplier_products.index');
Route::get('/agents/{agent}/supplier-products/{supplierProduct}', [ShowMarketplaceSupplierProduct::class, 'inAgent'])->name('agents.show.supplier_products.show');

Route::get('/agents/{agent}/suppliers/{supplier}', [ShowMarketplaceSupplier::class, 'inMarketplaceAgent'])->name('agents.show.suppliers.show');
Route::get('/agents/{agent}/suppliers/{supplier}/edit', [EditMarketplaceSupplier::class, 'inMarketplaceAgent'])->name('agents.show.suppliers.edit');

Route::get('/agents/{agent}/suppliers/{supplier}/supplier-products', [IndexMarketplaceSupplierProducts::class, 'inSupplierInAgent'])->name('agents.show.suppliers.show.supplier_products.index');
Route::get('/agents/{agent}/suppliers/{supplier}/supplier-products/{supplierProduct}', [ShowMarketplaceSupplierProduct::class, 'inSupplierInAgent'])->name('agents.show.suppliers.show.supplier_products.show');

Route::get('/suppliers', IndexSuppliers::class)->name('suppliers.index');
Route::get('/suppliers/create', CreateMarketplaceSupplier::class)->name('suppliers.create');
Route::get('/suppliers/{supplier}', ShowMarketplaceSupplier::class)->name('suppliers.show');
Route::get('/suppliers/{supplier}/edit', EditMarketplaceSupplier::class)->name('suppliers.edit');
Route::get('/suppliers/{supplier}/delete', RemoveMarketplaceSupplier::class)->name('suppliers.remove');
Route::get('/suppliers/{supplier}/supplier-products', [IndexMarketplaceSupplierProducts::class, 'inSupplier'])->name('suppliers.show.supplier_products.index');
Route::get('/suppliers/{supplier}/supplier-products/{supplierProduct}', [ShowMarketplaceSupplierProduct::class, 'inSupplier'])->name('suppliers.show.supplier_products.show');

Route::get('/supplier-products', [IndexMarketplaceSupplierProducts::class, 'inAgent'])->name('supplier_products.index');
Route::get('/supplier-products/{supplierProduct}', [ShowMarketplaceSupplierProduct::class, 'inAgent'])->name('supplier_products.show');
*/
