<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 12:21:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Upload\UI\IndexRecentUploads;
use App\Actions\Retina\Fulfilment\Pallet\DownloadRetinaPalletsTemplate;
use App\Actions\Retina\Fulfilment\Pallet\DownloadRetinaPalletsWithStoredItemsTemplate;
use App\Actions\Retina\Fulfilment\Pallet\UI\EditRetinaPallet;
use App\Actions\Retina\Fulfilment\Pallet\UI\IndexRetinaPallets;
use App\Actions\Retina\Fulfilment\Pallet\UI\ShowRetinaPallet;
use App\Actions\Retina\Fulfilment\PalletDelivery\UI\IndexRetinaPalletDeliveries;
use App\Actions\Retina\Fulfilment\PalletDelivery\UI\ShowRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletReturn\ExportRetinaPalletReturnPallet;
use App\Actions\Retina\Fulfilment\PalletReturn\ExportRetinaPalletReturnStoredItem;
use App\Actions\Retina\Fulfilment\PalletReturn\UI\IndexRetinaPalletReturns;
use App\Actions\Retina\Fulfilment\PalletReturn\UI\ShowRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\UI\ShowRetinaStoredItemReturn;
use App\Actions\Retina\Fulfilment\StoredItems\UI\IndexRetinaStoredItems;
use App\Actions\Retina\Fulfilment\StoredItemsAudit\UI\IndexRetinaStoredItemsAudits;
use App\Actions\Retina\Fulfilment\StoredItemsAudit\UI\ShowRetinaStoredItemAudit;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;

Route::redirect('/', 'dashboard');


Route::get('/dashboard', ShowRetinaStorageDashboard::class)->name('dashboard');

Route::prefix('pallet-deliveries')->as('pallet_deliveries.')->group(function () {
    Route::get('', IndexRetinaPalletDeliveries::class)->name('index');
    Route::get('{palletDelivery}', ShowRetinaPalletDelivery::class)->name('show');
    Route::get('{palletDelivery}/pallets/{pallet}', [ShowRetinaPallet::class, 'inPalletDelivery'])->name('pallets.show');
    Route::get('{palletDelivery}/pallets-templates', DownloadRetinaPalletsTemplate::class)->name('pallets.uploads.templates');
    Route::get('{palletDelivery}/pallet-stored-items-templates', DownloadRetinaPalletsWithStoredItemsTemplate::class)->name('pallet-stored-items.uploads.templates');
    Route::get('{palletDelivery}/pallets-histories', [IndexRecentUploads::class, 'inPalletRetina'])->name('pallets.uploads.history');
});

Route::prefix('pallet-returns')->as('pallet_returns.')->group(function () {
    Route::get('', IndexRetinaPalletReturns::class)->name('index');
    Route::get('{palletReturn}', ShowRetinaPalletReturn::class)->name('show');
    Route::get('{palletReturn}/with-stored-items', [ShowRetinaStoredItemReturn::class,'inStorage'])->name('with-stored-items.show');
    Route::get('{palletReturn}/pallets/{pallet}', [ShowRetinaPallet::class, 'inPalletReturn'])->name('pallets.show');
    Route::get('{fulfilmentCustomer}/stored-items-templates', ExportRetinaPalletReturnStoredItem::class)->name('stored-items.uploads.templates');
    Route::get('{fulfilmentCustomer}/pallets-templates', ExportRetinaPalletReturnPallet::class)->name('pallets.uploads.templates');
    Route::get('{palletReturn}/upload-histories', [IndexRecentUploads::class, 'inPalletReturnRetina'])->name('uploads.history');
});

Route::get('pallets', IndexRetinaPallets::class)->name('pallets.index');
Route::get('pallets/storing', [IndexRetinaPallets::class, 'storing'])->name('pallets.storing_pallets.index');
Route::get('pallets/returned', [IndexRetinaPallets::class, 'returned'])->name('pallets.returned_pallets.index');
Route::get('pallets/incidents', [IndexRetinaPallets::class, 'incidents'])->name('pallets.incidents_pallets.index');
Route::get('pallets/in_process', [IndexRetinaPallets::class, 'inProcess'])->name('pallets.in_process_pallets.index');


Route::get('pallets/{pallet}', ShowRetinaPallet::class)->name('pallets.show');
Route::get('pallets/{pallet}/edit', EditRetinaPallet::class)->name('pallets.edit');
Route::get('stored-items', IndexRetinaStoredItems::class)->name('stored-items.index');
Route::get('stored-items-audits', IndexRetinaStoredItemsAudits::class)->name('stored-items-audits.index');
Route::get('stored-items-audits/{storedItemAudit}', ShowRetinaStoredItemAudit::class)->name('stored-items-audits.show');
