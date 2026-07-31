<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\HumanResources\ClockingMachine\WithClockingKioskToken;
use App\Actions\HumanResources\Employee\SetEmployeePin;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowClockingKiosk
{
    use AsAction;
    use WithClockingKioskToken;

    public function asController(string $kioskToken, ActionRequest $request): Response
    {
        $clockingMachine = $this->resolveKioskMachine($kioskToken);

        $mode = $clockingMachine->type === ClockingMachineTypeEnum::BARCODE_SCANNER->value
            ? 'barcode'
            : 'pin';

        $this->assertKioskModeEnabled($clockingMachine, $mode);

        $pinCharacterSet = null;
        if ($mode === 'pin') {
            [$letters, $numbers] = SetEmployeePin::make()->pinCharacterSet();
            $pinCharacterSet = compact('letters', 'numbers');
        }

        return Inertia::render(
            'Org/HumanResources/ClockingKiosk',
            [
                'title'           => __('Employee Clocking'),
                'machineName'     => $clockingMachine->name,
                'kioskToken'      => $kioskToken,
                'mode'            => $mode,
                'pinCharacterSet' => $pinCharacterSet,
            ]
        );
    }
}
