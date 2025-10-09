<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:41:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Dispatching\DeliveryNote\PdfDeliveryNote;
use Illuminate\Support\Facades\Route;

Route::get('/delivery-notes/{deliveryNote}', PdfDeliveryNote::class)->name('delivery-notes');
