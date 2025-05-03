<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class EmployeeHydrateClockings implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Employee $employee): string
    {
        return $employee->id;
    }

    public function handle(Employee $employee): void
    {
        $stats = [
            'number_clockings' => $employee->clockings()->count(),
            'last_clocking_at' => $employee->clockings()->max('clocked_at') ?? null

        ];

        $employee->stats()->update($stats);
    }


}
