<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-24m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

use App\Actions\Api\Group\Fulfilment\BookInMobilePallet;
use App\Actions\Api\Group\Fulfilment\FinishBookingMobilePalletDelivery;
use App\Actions\Api\Group\Fulfilment\IndexMobileFulfilmentLocations;
use App\Actions\Api\Group\Fulfilment\IndexMobilePalletDeliveries;
use App\Actions\Api\Group\Fulfilment\IndexMobilePallets;
use App\Actions\Api\Group\Fulfilment\MoveMobilePallet;
use App\Actions\Api\Group\Fulfilment\ReceiveMobilePalletDelivery;
use App\Actions\Api\Group\Fulfilment\SetMobilePalletAsNotReceived;
use App\Actions\Api\Group\Fulfilment\ShowMobilePallet;
use App\Actions\Api\Group\Fulfilment\ShowMobilePalletDelivery;
use App\Actions\Api\Group\Fulfilment\StartBookingMobilePalletDelivery;
use App\Actions\Api\Group\Fulfilment\SetMobilePalletBackToStoring;
use App\Actions\Api\Group\Fulfilment\SetMobilePalletDeliveryAsNotReceived;
use App\Actions\Api\Group\Fulfilment\StoreMobilePalletIncident;
use App\Actions\Api\Group\Fulfilment\UndoMobilePalletNotReceived;
use Illuminate\Support\Facades\Route;

Route::get('/pallet-deliveries', IndexMobilePalletDeliveries::class)->name('pallet_deliveries.index');
Route::prefix('pallet-deliveries/{palletDelivery:id}')->name('pallet_deliveries.')->group(function () {
    Route::get('/', ShowMobilePalletDelivery::class)->name('show');
    Route::post('/receive', ReceiveMobilePalletDelivery::class)->name('receive');
    Route::post('/start-booking', StartBookingMobilePalletDelivery::class)->name('start_booking');
    Route::post('/finish-booking', FinishBookingMobilePalletDelivery::class)->name('finish_booking');
    Route::post('/not-received', SetMobilePalletDeliveryAsNotReceived::class)->name('not_received');
});

Route::get('/pallets', IndexMobilePallets::class)->name('pallets.index');
Route::prefix('pallets/{pallet:id}')->name('pallets.')->group(function () {
    Route::get('/', ShowMobilePallet::class)->name('show');
    Route::post('/book-in', BookInMobilePallet::class)->name('book_in');
    Route::post('/not-received', SetMobilePalletAsNotReceived::class)->name('not_received');
    Route::post('/undo-not-received', UndoMobilePalletNotReceived::class)->name('undo_not_received');
    Route::post('/move', MoveMobilePallet::class)->name('move');
    Route::post('/incident', StoreMobilePalletIncident::class)->name('incident');
    Route::post('/back-to-storing', SetMobilePalletBackToStoring::class)->name('back_to_storing');
});

Route::get('/fulfilment-locations', IndexMobileFulfilmentLocations::class)->name('fulfilment_locations.index');
