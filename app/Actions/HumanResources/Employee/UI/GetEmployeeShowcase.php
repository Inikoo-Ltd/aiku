<?php

/*
 * author Arya Permana - Kirin
 * created on 03-01-2025-10h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Actions\Traits\UI\WithPermissionsPictogram;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Employee;
use Lorisleiva\Actions\Concerns\AsObject;

class GetEmployeeShowcase
{
    use AsObject;
    use WithPermissionsPictogram;

    public function handle(Employee $employee): array
    {
        $user = $employee->getUser();

        if ($user) {
            $jobPositionsOrganisationsData = [];
            foreach ($employee->group->organisations as $organisation) {
                $jobPositionsOrganisationData                       = GetUserOrganisationScopeJobPositionsData::run($user, $organisation);
                $jobPositionsOrganisationsData[$organisation->slug] = $jobPositionsOrganisationData;
            }

            $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user);
            $pictogram            = $this->getPermissionsPictogram($user, $permissionsGroupData, $jobPositionsOrganisationsData);
        } else {
            $pictogram = null;
        }

        return [
            'employee'              => EmployeeResource::make($employee),
            'pin'                   => $employee->pin ? preg_replace('/^\d+:/', '', $employee->pin) : null,
            'regenerate_pin_route'  => route('grp.org.hr.employees.regenerate-pin', [$employee->organisation->slug, $employee->slug]),
            'permissions_pictogram' => $pictogram,
            'work_schedule'         => $this->getWorkScheduleData($employee),
        ];
    }

    private function getWorkScheduleData(Employee $employee): array
    {
        $ownSchedule  = $employee->getDefaultWorkSchedule();
        $workSchedule = $ownSchedule ?? $employee->organisation->getDefaultWorkSchedule();

        if (!$workSchedule) {
            return [
                'source' => null,
                'days'   => [],
            ];
        }

        $workSchedule->load('days.breaks');

        $days = $workSchedule->days->sortBy('day_of_week')->map(fn ($day) => [
            'day_of_week' => $day->day_of_week,
            'start_time'  => $day->start_time,
            'end_time'    => $day->end_time,
            'breaks'      => $day->breaks->map(fn ($break) => [
                'name'       => $break->break_name,
                'start_time' => $break->start_time?->format('H:i'),
                'end_time'   => $break->end_time?->format('H:i'),
            ])->values(),
        ])->values();

        return [
            'source' => $ownSchedule ? 'employee' : 'organisation',
            'days'   => $days,
        ];
    }
}
