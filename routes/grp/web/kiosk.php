<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * Public (unauthenticated) kiosk routes.
 *
 * These are deliberately outside the auth/two_fa group so a wall tablet can display the
 * rotating clocking QR code without keeping an admin session open on a shared device.
 * Access is gated by the unguessable per machine clocking_machines.kiosk_token.
 * The QR itself is not a credential: the scanning employee still authenticates on their
 * own device when posting to grp.models.clocking-machine.qr.validate.
 */

use App\Actions\HumanResources\ClockingMachine\GenerateKioskQrCode;
use App\Actions\HumanResources\ClockingMachine\UI\ShowClockingKiosk;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:kiosk')->prefix('clocking-kiosk')->group(function () {
    Route::get('{kioskToken}', ShowClockingKiosk::class)->name('show');
    Route::get('{kioskToken}/qr', GenerateKioskQrCode::class)->name('qr');
});
