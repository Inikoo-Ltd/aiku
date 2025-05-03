<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 10:16:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateJobPositionsShare;
use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateEmployees;
use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use Lorisleiva\Actions\Concerns\AsObject;

class SyncEmployeeJobPositions
{
    use AsObject;

    public function handle(Employee $employee, array $jobPositions): void
    {

        $jobPositionsIds = array_keys($jobPositions);
        $currentJobPositions = $employee->jobPositions()->pluck('job_positions.id')->all();

        $newJobPositionsIds = array_diff($jobPositionsIds, $currentJobPositions);
        $removeJobPositions = array_diff($currentJobPositions, $jobPositionsIds);
        $jobPositionsToUpdate = array_intersect($jobPositionsIds, $currentJobPositions);

        $employee->jobPositions()->detach($removeJobPositions);

        foreach ($newJobPositionsIds as $jobPositionId) {
            $employee->jobPositions()->attach(
                [
                    $jobPositionId => [
                        'group_id'        => $employee->group_id,
                        'organisation_id' => $employee->organisation_id,
                        'scopes'          => $jobPositions[$jobPositionId]
                    ]
                ],
            );
        }



        foreach ($jobPositionsToUpdate as $jobPositionId) {
            $employee->jobPositions()->updateExistingPivot(
                $jobPositionId,
                [
                    'scopes' => $jobPositions[$jobPositionId]
                ]
            );
        }




        foreach ($employee->users as $user) {
            SyncRolesFromJobPositions::run($user);
        }

        if (count($newJobPositionsIds) || count($removeJobPositions)) {
            EmployeeHydrateJobPositionsShare::run($employee);
            foreach ($removeJobPositions as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }

            foreach ($newJobPositionsIds as $jobPositionId) {
                $jobPosition = JobPosition::find($jobPositionId);
                JobPositionHydrateEmployees::dispatch($jobPosition);
            }
        }
    }
}
