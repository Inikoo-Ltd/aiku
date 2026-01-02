<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 23:29:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 23:38:00 Makassar Time
 * Description: Routes for OrderReturn models
 */

use App\Actions\GoodsIn\Return\UpdateState\SetReturnAsReceived;
use Illuminate\Support\Facades\Route;

Route::name('return.')->prefix('return/{return:id}')->group(function () {
    Route::patch('receive', SetReturnAsReceived::class)->name('receive');
});
