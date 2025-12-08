<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Jan 2025 13:03:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\HumanResources\Employee\GetEmployeeJobPositionsData;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserOrganisationScopeJobPositionsData
{
    use AsAction;

    public function handle(User $user, Organisation $organisation): array
    {

        $organisationsWhereUserIsEmployee = $user->employees()->pluck('employees.organisation_id')->toArray();

        if (in_array($organisation->id, $organisationsWhereUserIsEmployee)) {
            // get job positions data from the employee
            $employee = $user->employees()->where('employees.organisation_id', $organisation->id)->first();

            return GetEmployeeJobPositionsData::run($employee);
        } else {
            return $user->pseudoJobPositions()->wherePivot('user_has_pseudo_job_positions.organisation_id', $organisation->id)->get()->map(function ($jobPosition) use ($organisation) {
                $scopes = collect($jobPosition->pivot->scopes)->mapWithKeys(function ($scopeIds, $scope) use ($organisation) {
                    return match ($scope) {
                        'Warehouse' => [
                            'warehouses' => $organisation->warehouses->whereIn('id', $scopeIds)->pluck('slug')->toArray(),
                        ],
                        'Shop' => [
                            'shops' => $organisation->shops->whereIn('id', $scopeIds)->pluck('slug')->toArray(),
                        ],
                        'Fulfilment' => [
                            'fulfilments' => $organisation->fulfilments->whereIn('id', $scopeIds)->pluck('slug')->toArray(),
                        ],
                        default => []
                    };
                });

                return [$jobPosition->code => $scopes->toArray()];
            })->reduce(function ($carry, $item) {
                return array_merge_recursive($carry, $item);
            }, []);
        }
    }
}
