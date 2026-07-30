<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\HumanResources\ClockingMachine\WithClockingKioskToken;
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

        return Inertia::render(
            'Org/HumanResources/ClockingKiosk',
            [
                'title'       => __('Employee Scan'),
                'machineName' => $clockingMachine->name,
                'kioskToken'  => $kioskToken,
            ]
        );
    }
}
