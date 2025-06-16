<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Dec 2024 15:57:14 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Inventory\Location\DeleteLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use Illuminate\Support\Facades\Route;

Route::name('location.')->prefix('location/{location:id}')->group(function () {
    Route::patch('', UpdateLocation::class)->name('update');
    Route::delete('delete', DeleteLocation::class)->name('delete');
});
