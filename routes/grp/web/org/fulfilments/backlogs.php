<?php

/*
 * author Louis Perez
 * created on 17-04-2026-15h-59m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

use App\Actions\Fulfilment\PalletReturn\UI\IndexPalletReturns;
use App\Actions\Fulfilment\PalletReturn\UI\ShowPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\ShowPalletReturnsBacklog;
use App\Actions\Fulfilment\PalletReturn\UI\ShowStoredItemReturn;
use Illuminate\Support\Facades\Route;

// Route::get('returns', IndexPalletReturns::class)->name('pallet-returns.index');
// Route::get('returns/confirmed', [IndexPalletReturns::class, 'inFulfilmentConfirmed'])->name('pallet-returns.confirmed.index');
// Route::get('returns/picking', [IndexPalletReturns::class, 'inFulfilmentPicking'])->name('pallet-returns.picking.index');
// Route::get('returns/picked', [IndexPalletReturns::class, 'inFulfilmentPicked'])->name('pallet-returns.picked.index');
// Route::get('returns/dispatched', [IndexPalletReturns::class, 'inFulfilmentDispatched'])->name('pallet-returns.dispatched.index');
// Route::get('returns/cancelled', [IndexPalletReturns::class, 'inFulfilmentCancelled'])->name('pallet-returns.cancelled.index');
// Route::get('returns/new', [IndexPalletReturns::class, 'inFulfilmentNew'])->name('pallet-returns.new.index');

// Route::get('return-with-stored-items/{palletReturn}', ShowStoredItemReturn::class)->name('pallet-return-with-stored-items.show');

Route::prefix('return-backlog')->as('pallet-returns-backlog.')->group(function () {
    Route::prefix('wholesale/')->as('wholesale.')->group(function () {
        Route::get('/', ShowPalletReturnsBacklog::class)->name('index');
        Route::get('/{palletReturn}', ShowPalletReturn::class)->name('pallet-returns.show');
    });


    Route::prefix('dropship/')->as('dropship.')->group(function () {
        Route::get('/', [ShowPalletReturnsBacklog::class, 'inDropshipping'])->name('index');
        Route::get('/{palletReturn}', ShowStoredItemReturn::class)->name('pallet-returns.show');
    });


});
