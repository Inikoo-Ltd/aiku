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
     * Anything other than an exact match on a non empty token for a machine with at least one
     * kiosk mode (pin, barcode, ...) enabled is a 404, so an invalid, revoked or disabled kiosk
     * link leaks nothing.
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

        if (!$this->kioskModeEnabled($clockingMachine, 'pin') && !$this->kioskModeEnabled($clockingMachine, 'barcode')) {
            throw new NotFoundHttpException();
        }

        return $clockingMachine;
    }

    protected function kioskModeEnabled(ClockingMachine $clockingMachine, string $mode): bool
    {
        return (bool) data_get($clockingMachine->config, "{$mode}.enable", false);
    }

    /**
     * A machine's kiosk_token gates access to the kiosk generally; individual endpoints
     * (pin submit, barcode submit, ...) must additionally assert their own mode is enabled,
     * since a machine can have its token active for one mode but not another.
     */
    protected function assertKioskModeEnabled(ClockingMachine $clockingMachine, string $mode): void
    {
        if (!$this->kioskModeEnabled($clockingMachine, $mode)) {
            throw new NotFoundHttpException();
        }
    }
}
