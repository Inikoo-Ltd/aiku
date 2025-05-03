<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 10:42:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\Hydrators;

use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateJobPositionsShare;
use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithNormalise;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class JobPositionHydrateEmployees implements ShouldBeUnique
{
    use AsAction;
    use WithNormalise;
    use WithEnumStats;

    public function getJobUniqueId(JobPosition $jobPosition): string
    {
        return $jobPosition->id;
    }

    public function handle(JobPosition $jobPosition): void
    {


        $numberEmployees        = $jobPosition->employees()->count();
        $numberEmployeesWorkTime = $jobPosition->employees()->sum('share');


        $stats = [
            'number_employees'           => $numberEmployees,
            'number_employees_work_time' => $numberEmployeesWorkTime
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'employees',
                field: 'state',
                enum: EmployeeStateEnum::class,
                models: Employee::class,
                where: function ($q) use ($jobPosition) {
                    $q->leftJoin('employee_has_job_positions', 'employee_has_job_positions.employee_id', '=', 'employees.id')
                        ->where('job_position_id', $jobPosition->id);
                }
            )
        );

        $stats['number_employees_currently_working'] = $stats['number_employees_state_working'] + $stats['number_employees_state_leaving'];

        $jobPosition->stats()->update($stats);
        if ($jobPosition->organisation) {
            OrganisationHydrateJobPositionsShare::run($jobPosition->organisation);
        }
    }




}
