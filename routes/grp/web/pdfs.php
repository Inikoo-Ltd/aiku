<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:41:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Dispatching\DeliveryNote\PdfDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\PdfPackingList;
use App\Actions\Ordering\Order\PdfOrderGiftMessage;
use Illuminate\Support\Facades\Route;

Route::get('/delivery-notes/{deliveryNote}', PdfDeliveryNote::class)->name('delivery-notes');
Route::get('/packing-lists/{deliveryNote}', PdfPackingList::class)->name('packing-lists');
Route::get('/order-gift-message/{order}', PdfOrderGiftMessage::class)->name('order-gift-message');
