<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 15:40:33 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInLocation;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemPallets;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInWarehouse;
use App\Actions\Fulfilment\StoredItem\UI\ShowStoredItem;
use App\Actions\Fulfilment\StoredItemAudit\UI\CreateStoredItemAuditFromPalletInWarehouse;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Inventory\OrgStock\UI\ShowOrgStock;
use App\Actions\Fulfilment\StoredItemAudit\UI\ShowStoredItemAuditForPallet;
use Illuminate\Support\Facades\Route;

Route::prefix('stocks')->as('org_stocks.')->group(function () {
    Route::get('/', IndexOrgStocks::class)->name('index');
    Route::get('{orgStock:id}', ShowOrgStock::class)->name('show')->withoutScopedBindings();
});

Route::prefix('locations')->as('locations.')->group(function () {
    Route::get('{location:id}/pallets', IndexPalletsInLocation::class)->name('pallets.index');
});

Route::prefix('pallets')->as('pallets.')->group(function () {
    Route::get('/', IndexPalletsInWarehouse::class)->name('index');
    Route::get('{pallet:id}', ShowPallet::class)->name('show');
    Route::get('{pallet:id}/stored-item-audits/create', CreateStoredItemAuditFromPalletInWarehouse::class)->name('show.stored-item-audit.create')->withoutScopedBindings();
    Route::get('{pallet:id}/stored-item-audit/{storedItemAudit:id}', [ShowStoredItemAuditForPallet::class, 'inWarehouse'])->name('show.stored-item-audit.show')->withoutScopedBindings();
});

Route::prefix('stored-items')->as('stored-items.')->group(function () {
    Route::get('/', IndexStoredItemsInWarehouse::class)->name('index');
    Route::get('{storedItem:id}', ShowStoredItem::class)->name('show')->withoutScopedBindings();
    Route::get('{storedItem:id}/pallets', [IndexStoredItemPallets::class, 'inStoredItem'])->name('pallets.index')->withoutScopedBindings();
});
