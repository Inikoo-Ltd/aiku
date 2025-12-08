<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 09:40:59 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\UI\ShowAgentInventoryDashboard;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexAgentSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\UI\ShowSupplierProduct;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowAgentInventoryDashboard::class)->name('dashboard');
Route::get('/skus', IndexAgentSupplierProducts::class)->name('supplier_products.index');
Route::get('/skus/{supplierProduct:id}', ShowSupplierProduct::class)->name('supplier_products.show');
