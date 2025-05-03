<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Jan 2025 13:00:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Models\HumanResources\Employee;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEmployeeJobPositionsData
{
    use AsAction;

    public function handle(Employee $employee): array
    {
        $organisation = $employee->organisation;
        return $employee->jobPositions->map(function ($jobPosition) use ($organisation) {
            $scopes = collect($jobPosition->pivot->scopes)->mapWithKeys(function ($scopeIds, $scope) use ($organisation) {
                return match ($scope) {
                    'Warehouse' => [
                        'warehouses' => $organisation->warehouses->whereIn('id', $scopeIds)->pluck('slug')->toArray()
                    ],
                    'Shop' => [
                        'shops' => $organisation->shops->whereIn('id', $scopeIds)->pluck('slug')->toArray()
                    ],
                    'Fulfilment' => [
                        'fulfilments' => $organisation->fulfilments->whereIn('id', $scopeIds)->pluck('slug')->toArray()
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
