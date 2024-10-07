<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 23 Feb 2024 09:01:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\Pallet\DownloadPalletsTemplate;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\PalletReturn\ExportPalletReturnPallet;
use App\Actions\Fulfilment\PalletReturn\ExportPalletReturnStoredItem;
use App\Actions\Helpers\Upload\HistoryUploads;
use App\Actions\Retina\Storage\Pallet\UI\IndexPallets;
use App\Actions\Retina\Storage\PalletDelivery\UI\IndexPalletDeliveries;
use App\Actions\Retina\Storage\PalletDelivery\UI\ShowPalletDelivery;
use App\Actions\Retina\Storage\PalletReturn\UI\IndexPalletReturns;
use App\Actions\Retina\Storage\PalletReturn\UI\ShowPalletReturn;
use App\Actions\Retina\Storage\StoredItems\UI\IndexStoredItems;
use App\Actions\UI\Retina\Storage\UI\ShowStorageDashboard;

Route::get('/dashboard', ShowStorageDashboard::class)->name('dashboard');

Route::prefix('pallet-deliveries')->as('pallet-deliveries.')->group(function () {
    Route::get('', IndexPalletDeliveries::class)->name('index');
    Route::get('{palletDelivery}', ShowPalletDelivery::class)->name('show');
    Route::get('{palletDelivery}/pallets/{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('pallets.show');
    Route::get('{palletDelivery}/pallets-templates', DownloadPalletsTemplate::class)->name('pallets.uploads.templates');
    Route::get('{palletDelivery}/pallets-histories', [HistoryUploads::class, 'inPalletRetina'])->name('pallets.uploads.history');
});

Route::prefix('pallet-returns')->as('pallet-returns.')->group(function () {
    Route::get('', IndexPalletReturns::class)->name('index');
    Route::get('{palletReturn}', ShowPalletReturn::class)->name('show');
    Route::get('{palletReturn}/pallets/{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('pallets.show');
    Route::get('{fulfilmentCustomer}/stored-items-templates', [ExportPalletReturnStoredItem::class, 'fromRetina'])->name('stored-items.uploads.templates');
    Route::get('{fulfilmentCustomer}/pallets-templates', [ExportPalletReturnPallet::class, 'fromRetina'])->name('pallets.uploads.templates');
    Route::get('{palletReturn}/upload-histories', [HistoryUploads::class, 'inPalletReturnRetina'])->name('uploads.history');
});

Route::get('pallets', IndexPallets::class)->name('pallets.index');
Route::get('pallets/{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('pallets.show');
Route::get('stored-items', IndexStoredItems::class)->name('stored-items.index');
