<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:28 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\HumanResources\ClockingMachine\Hydrators\ClockingMachineHydrateClockings;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\HumanResources\ClockingMachine;

class HydrateClockingMachine
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:clocking-machine {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = ClockingMachine::class;
    }


    public function handle(ClockingMachine $clockingMachine): void
    {
        ClockingMachineHydrateClockings::run($clockingMachine);
    }

}
