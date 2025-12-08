<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Aug 2024 15:17:43 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */
use Illuminate\Support\Facades\Route;

Route::prefix('hr')
    ->name('hr.')
    ->group(__DIR__.'/hr.php');

Route::prefix('warehouses')
    ->name('warehouses.')
    ->group(__DIR__.'/warehouses/warehouses.php');

Route::prefix('fulfilment/{fulfilment:id}')
    ->name('fulfilment.')
    ->group(__DIR__.'/fulfilment.php');
