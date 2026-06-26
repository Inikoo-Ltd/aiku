<?php

/*
 * Author: Vika Aqordi
 * Created on 06-05-2026-12h-39m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

use App\Actions\GoodsIn\ReturnDeliveryNoteItem\SetReturnDeliveryNoteItemAsNotReturned;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\SetReturnDeliveryNoteItemAsReturned;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\SetReturnDeliveryNoteItemAsDamaged;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpsertReturnDeliveryNoteItemNotReturned;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpsertReturnDeliveryNoteItemReturned;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\UpsertReturnDeliveryNoteItemDamaged;
use Illuminate\Support\Facades\Route;

Route::name('return_delivery_note_item.')->prefix('return-delivery-note-item/{returnDeliveryNoteItem:id}')->group(function () {
    Route::patch('upsert-not-returned', UpsertReturnDeliveryNoteItemNotReturned::class)->name('upsert_not_returned');
    Route::patch('set-all-not-returned', SetReturnDeliveryNoteItemAsNotReturned::class)->name('set_all_not_returned');

    Route::patch('upsert-returned', UpsertReturnDeliveryNoteItemReturned::class)->name('upsert_returned');
    Route::patch('set-all-returned', SetReturnDeliveryNoteItemAsReturned::class)->name('set_all_returned');

    Route::patch('upsert-damaged', UpsertReturnDeliveryNoteItemDamaged::class)->name('upsert_damaged');
    Route::patch('set-all-damaged', SetReturnDeliveryNoteItemAsDamaged::class)->name('set_all_damaged');
});
