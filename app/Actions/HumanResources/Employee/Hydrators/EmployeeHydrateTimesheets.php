<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 19:27:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\HumanResources\Employee;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class EmployeeHydrateTimesheets implements ShouldBeUnique
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
            'number_timesheets' => $employee->timesheets()->count(),
        ];

        $employee->stats()->update($stats);
    }


}
