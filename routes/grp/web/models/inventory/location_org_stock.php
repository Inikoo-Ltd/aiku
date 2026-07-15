<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:18:41 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\SetOrgStockPreferredSupplierProduct;
use Illuminate\Support\Facades\Route;

Route::name('org_stock.')->prefix('org-stock/{orgStock:id}')->group(function () {
    Route::post('location/{location:id}', StoreLocationOrgStock::class)->name('location.store')->withoutScopedBindings();
    Route::patch('supplier-product/{orgSupplierProduct:id}/set-preferred', SetOrgStockPreferredSupplierProduct::class)->name('supplier_product.set_preferred')->withoutScopedBindings();
});

Route::name('location_org_stock.')->prefix('location-org-stock/{locationOrgStock:id}')->group(function () {
    Route::delete('', DeleteLocationOrgStock::class)->name('delete');
    Route::patch('audit', AuditLocationOrgStock::class)->name('audit');
    Route::patch('move/{targetLocationOrgStock:id}', MoveOrgStockToOtherLocation::class)->name('move')->withoutScopedBindings();
    Route::patch('', UpdateLocationOrgStock::class)->name('update');
});
