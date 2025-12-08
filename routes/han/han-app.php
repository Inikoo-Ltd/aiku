<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 15:37:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\ClockingMachine\ConnectClockingMachineToHan;
use App\Actions\HumanResources\ClockingMachine\DisconnectClockingMachineFromHan;
use App\Actions\HumanResources\Employee\ValidatePinEmployee;
use Illuminate\Support\Facades\Route;

Route::name('han.')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::prefix('employee')->group(function () {
            Route::post('pin', ValidatePinEmployee::class)->name('employee.pin.validate');
            Route::post('{employee:id}/clocking', [StoreClocking::class, 'han'])->name('employee.clocking.store');
        });

        Route::delete('disconnect', DisconnectClockingMachineFromHan::class)->name('disconnect');
    });

    Route::post('connect', ConnectClockingMachineToHan::class)->name('connect');
});
