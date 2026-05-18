<?php

/*
 * author Louis Perez
 * created on 05-05-2026-10h-29m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

use App\Actions\GoodsIn\ReturnDeliveryNote\CancelReturnDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\SetDoneReturnDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\SetReturnedReturnDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\SetReturningReturnDeliveryNote;
use App\Actions\GoodsIn\ReturnDeliveryNote\UnassignReturnDeliveryNoteHandler;
use App\Actions\GoodsIn\ReturnDeliveryNote\UpdateReturnDeliveryNote;
use Illuminate\Support\Facades\Route;

Route::name('return_delivery_note.')->prefix('return-delivery-note/{returnDeliveryNote:id}')->group(function () {
    Route::patch('unassign', UnassignReturnDeliveryNoteHandler::class)->name('unassign');
    Route::patch('update', UpdateReturnDeliveryNote::class)->name('update');

    Route::name('state.')->prefix('state')->group(function () {
        Route::patch('cancel', CancelReturnDeliveryNote::class)->name('cancel');
        Route::patch('handling', SetReturningReturnDeliveryNote::class)->name('returning');
        Route::patch('returned', SetReturnedReturnDeliveryNote::class)->name('returned');
        Route::patch('done', SetDoneReturnDeliveryNote::class)->name('done');
    });
});
