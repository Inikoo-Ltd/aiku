<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * Public (unauthenticated) kiosk routes.
 *
 * These are deliberately outside the auth/two_fa group so a wall tablet can show the
 * PIN entry screen and let an employee clock in/out directly, without keeping an admin
 * session open on a shared device. Access is gated by the unguessable per machine
 * clocking_machines.kiosk_token.
 */

use App\Actions\HumanResources\ClockingMachine\UI\ShowClockingKiosk;
use App\Actions\HumanResources\ClockingMachine\ValidateClockingKioskPin;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:kiosk')->prefix('clocking-kiosk')->group(function () {
    Route::get('{kioskToken}', ShowClockingKiosk::class)->name('show');
    Route::post('{kioskToken}/pin', ValidateClockingKioskPin::class)->name('pin.submit');
});
