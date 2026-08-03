<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Models\HumanResources\ClockingMachine;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait WithClockingKioskToken
{
    /**
     * Resolve a clocking machine from its public kiosk token.
     *
     * Anything other than an exact match on a non empty token for a machine with QR clocking
     * enabled is a 404, so an invalid, revoked or disabled kiosk link leaks nothing.
     */
    protected function resolveKioskMachine(string $kioskToken): ClockingMachine
    {
        if (trim($kioskToken) === '') {
            throw new NotFoundHttpException();
        }

        $clockingMachine = ClockingMachine::where('kiosk_token', $kioskToken)->first();

        if (!$clockingMachine) {
            throw new NotFoundHttpException();
        }

        if (!($clockingMachine->config['qr']['enable'] ?? false)) {
            throw new NotFoundHttpException();
        }

        return $clockingMachine;
    }
}
