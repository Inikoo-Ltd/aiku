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
            'pin'                   => $employee->pin,
            'permissions_pictogram' => $pictogram
        ];
    }
}
