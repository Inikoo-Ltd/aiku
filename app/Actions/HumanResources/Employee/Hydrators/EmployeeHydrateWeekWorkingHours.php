<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 16:02:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Hydrators;

use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class EmployeeHydrateWeekWorkingHours implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Employee $employee): int
    {
        return $employee->id;
    }

    public function handle(Employee $employee): void
    {
        $week_working_hours = Arr::get($employee->working_hours, 'week_distribution.sunday', 0) +
            Arr::get($employee->working_hours, 'week_distribution.saturday', 0)                 +
            Arr::get($employee->working_hours, 'week_distribution.weekdays', 0);

        $employee->update(['week_working_hours' => $week_working_hours]);
    }

}
