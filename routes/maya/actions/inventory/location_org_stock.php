<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 00:20:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\LocationOrgStock\AuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\BulkAuditLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\DeleteLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\MoveOrgStockToOtherLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\LocationOrgStock\UpdateLocationOrgStock;
use Illuminate\Support\Facades\Route;

Route::name('org_stock.')->prefix('org-stock/{orgStock:id}')->group(function () {
    Route::post('location/{location:id}', StoreLocationOrgStock::class)->name('location.store')->withoutScopedBindings();
    Route::patch('bulk_audit', BulkAuditLocationOrgStock::class)->name('bulk_audit');
});

Route::name('location_org_stock.')->prefix('location-org-stock/{locationOrgStock:id}')->group(function () {
    Route::delete('', DeleteLocationOrgStock::class)->name('delete');
    Route::patch('audit', AuditLocationOrgStock::class)->name('audit');
    Route::patch('move/{targetLocationOrgStock:id}', MoveOrgStockToOtherLocation::class)->name('move')->withoutScopedBindings();
    Route::patch('', UpdateLocationOrgStock::class)->name('update');

});
