<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Apr 2026 22:34:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI\Traits;

use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

trait HasPermissionsForm
{
    protected function getPermissionsFormData(User $user): array
    {
        $jobPositionsOrganisationsData = [];
        foreach ($this->group->organisations as $organisation) {
            $jobPositionsOrganisationsData[$organisation->slug] = GetUserOrganisationScopeJobPositionsData::run($user, $organisation);
        }

        $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user);
        $organisations = $user->group->organisations;
        $orgIds = $user->getOrganisations()->pluck('id')->toArray();

        $reviewData = $organisations->mapWithKeys(function ($organisation) use ($user, $orgIds) {
            /** @var Organisation $organisation */
            return [
                $organisation->slug => [
                    'is_employee'          => in_array($organisation->id, $orgIds),
                    'number_job_positions' => $organisation->humanResourcesStats->number_job_positions,
                    'job_positions'        => $organisation->jobPositions->mapWithKeys(function ($jobPosition) {
                        /** @var JobPosition $jobPosition */
                        return [
                            $jobPosition->slug => [
                                'job_position' => $jobPosition->name,
                                'number_roles' => $jobPosition->stats->number_roles,
                            ],
                        ];
                    }),
                ],
            ];
        })->toArray();

        return [
            'reviewData'                    => $reviewData,
            'organisationList'              => OrganisationsResource::collection($organisations),
            'jobPositionsOrganisationsData' => $jobPositionsOrganisationsData,
            'permissionsGroupData'          => $permissionsGroupData,
        ];
    }

    protected function getPermissionsFieldDefinition(User $user, array $permissionsData, ?Employee $employee = null): array
    {
        $field = [
            'full'                          => true,
            'noSaveButton'                  => true,
            'type'                          => 'permissions',
            'review'                        => $permissionsData['reviewData'],
            'organisation_list'             => $permissionsData['organisationList'],
            'current_organisation'          => $user->getOrganisation(),
            'updatePseudoJobPositionsRoute' => [
                'method'     => 'patch',
                'name'       => 'grp.models.user.group_permissions.update',
                'parameters' => [
                    'user' => $user->id,
                ],
            ],
            'updateJobPositionsRoute'       => [
                'method'     => 'patch',
                'name'       => 'grp.models.user.organisation_pseudo_job_positions.update',
                'parameters' => [
                    'user'         => $user->id,
                    'organisation' => null, // fill this id in the frontend
                ],
            ],
            'options'                       => Organisation::get()->flatMap(function (Organisation $organisation) {
                return [
                    $organisation->slug => [
                        'positions'   => JobPositionResource::collection($organisation->jobPositions),
                        'shops'       => ShopResource::collection($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                        'fulfilments' => ShopResource::collection($organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                        'warehouses'  => WarehouseResource::collection($organisation->warehouses),
                    ],
                ];
            })->toArray(),
            'value'                         => [
                'group'         => $permissionsData['permissionsGroupData'],
                'organisations' => $permissionsData['jobPositionsOrganisationsData'],
            ],
            'fullComponentArea'             => true,
        ];

        if ($employee) {
            $field['updateEmployeeJobPositionsRoute'] = [
                'method'     => 'patch',
                'name'       => 'grp.models.employee.update',
                'parameters' => [
                    'employee' => $employee->id,
                ],
            ];
        }

        return $field;
    }
}
