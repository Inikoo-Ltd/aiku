<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:28 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateJobPositionsShare;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimesheets;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimeTracker;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateWeekWorkingHours;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\HumanResources\Employee;

class HydrateEmployees
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:employees {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Employee::class;
    }

    public function handle(Employee $employee): void
    {
        EmployeeHydrateJobPositionsShare::run($employee);
        EmployeeHydrateWeekWorkingHours::run($employee);
        EmployeeHydrateTimesheets::run($employee);
        EmployeeHydrateClockings::run($employee);
        EmployeeHydrateTimeTracker::run($employee);
    }

}
