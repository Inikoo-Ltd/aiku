<?php

/*
 * Author: Vika Aqordi
 * Created on 06-05-2026-12h-39m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

use Illuminate\Support\Facades\Route;

Route::name('return_delivery_note_item.')->prefix('return-delivery-note-item/{returnDeliveryNoteItem:id}')->group(function () {
    Route::patch('not-founded', function () {
        dd('not_founded');
    })->name('not_founded');

    Route::patch('returned', function () {
        dd('returned');
    })->name('returned');

    Route::patch('damage', function () {
        dd('damage');
    })->name('damage');
});
