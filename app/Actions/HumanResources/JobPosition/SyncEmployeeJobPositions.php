<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 10:16:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateJobPositionsShare;
use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateEmployees;
use App\Actions\SysAdmin\CleanUserCaches;
use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

class SyncEmployeeJobPositions
{
    use AsObject;

    public function handle(Employee $employee, array $jobPositions): void
    {
        $jobPositions = DropLowerGradeJobPositionScopes::run($jobPositions);
        $positionsBefore     = $this->positionsForAudit($employee);
        $jobPositionsIds     = array_keys($jobPositions);
        $currentJobPositions = $employee->jobPositions()->pluck('job_positions.id')->all();

        $newJobPositionsIds   = array_diff($jobPositionsIds, $currentJobPositions);
        $removeJobPositions   = array_diff($currentJobPositions, $jobPositionsIds);
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


        $positionsAfter = $this->positionsForAudit($employee);
        if ($positionsBefore != $positionsAfter) {
            $employee->auditEvent     = 'job_positions';
            $employee->isCustomEvent  = true;
            $employee->auditCustomOld = $positionsBefore;
            $employee->auditCustomNew = $positionsAfter;
            Event::dispatch(new AuditCustom($employee));
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

        foreach ($employee->users as $user) {
            CleanUserCaches::run($user);
            BreakUserUiProps::dispatch($user);
        }
    }

    private function positionsForAudit(Employee $employee): array
    {
        return $employee->jobPositions()->get()
            ->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->name => $jobPosition->pivot->scopes])
            ->sortKeys()
            ->all();
    }
}
