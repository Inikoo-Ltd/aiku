<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Dec 2023 23:19:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Comms\Unsubscribe\ShowUnsubscribeMailshot;
use App\Actions\Comms\Unsubscribe\UnsubscribeMailshot;
use Illuminate\Support\Facades\Route;

Route::get('{dispatchedEmail:id}', ShowUnsubscribeMailshot::class)->name('show');
Route::post('{dispatchedEmail:id}', UnsubscribeMailshot::class)->name('update');
