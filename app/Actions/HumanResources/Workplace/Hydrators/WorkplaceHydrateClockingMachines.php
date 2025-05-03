<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jan 2024 21:08:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Workplace\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Workplace;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WorkplaceHydrateClockingMachines implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Workplace $workplace): string
    {
        return $workplace->id;
    }

    public function handle(Workplace $workplace): void
    {

        $stats = [
            'number_clocking_machines' => $workplace->clockingMachines()->count()
        ];
        $stats = array_merge($stats, $this->getEnumStats(
            model:'clocking_machines',
            field: 'type',
            enum: ClockingMachineTypeEnum::class,
            models: ClockingMachine::class,
            where: function ($q) use ($workplace) {
                $q->where('workplace_id', $workplace->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model:'clocking_machines',
            field: 'status',
            enum: ClockingMachineStatusEnum::class,
            models: ClockingMachine::class,
            where: function ($q) use ($workplace) {
                $q->where('workplace_id', $workplace->id);
            }
        ));

        $workplace->stats()->update($stats);
    }
}
