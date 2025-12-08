<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 11:51:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Models\HumanResources\ClockingMachine;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateClockingMachines
{
    use AsAction;
    use WithEnumStats;

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_clocking_machines' => $organisation->clockingMachines()->count(),
        ];
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'clocking_machines',
                field: 'type',
                enum: ClockingMachineTypeEnum::class,
                models: ClockingMachine::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'clocking_machines',
                field: 'status',
                enum: ClockingMachineStatusEnum::class,
                models: ClockingMachine::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        $organisation->humanResourcesStats()->update($stats);
    }
}
