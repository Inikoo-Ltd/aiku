<?php

/*
 * author Louis Perez
 * created on 05-05-2026-10h-29m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

use App\Actions\Dispatching\DeliveryNote\Return\CancelReturnDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\Return\SetHandlingReturnDeliveryNote;
use Illuminate\Support\Facades\Route;

Route::name('return_delivery_note.')->prefix('return-delivery-note/{returnDeliveryNote:id}')->group(function () {
    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('cancel', CancelReturnDeliveryNote::class)->name('cancel');
        Route::patch('handling', SetHandlingReturnDeliveryNote::class)->name('handling');
    });
});
