<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Apr 2024 15:09:47 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Wowsbar;

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\User;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchWowsbarEmployees extends FetchWowsbarAction
{
    public string $commandSignature = 'fetch:wow_employees {organisations?*} {--s|source_id=} ';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Employee
    {
        setPermissionsTeamId($organisationSource->getOrganisation()->group_id);

        if ($employeeData = $organisationSource->fetchEmployee($organisationSourceId)) {
            if ($employee = Employee::where('source_id', $employeeData['employee']['source_id'])->first()) {
                $employee = UpdateEmployee::make()->action(
                    employee: $employee,
                    modelData: $employeeData['employee'],
                    hydratorsDelay: 60,
                    strict: false,
                    audit: false
                );
            } else {
                /* @var $workplace Workplace */
                $workplace = $organisationSource->getOrganisation()->workplaces()->first();
                $employee = StoreEmployee::make()->action(
                    parent: $workplace,
                    modelData: $employeeData['employee'],
                    hydratorsDelay: 60,
                    strict: false
                );
            }

            if (Arr::has($employeeData, 'user.source_id')) {
                if ($user = User::where('source_id', $employeeData['user']['source_id'])->first()) {
                    UpdateUser::make()->action(
                        user: $user,
                        modelData: $employeeData['user'],
                        hydratorsDelay: 60,
                        strict: false,
                    );
                } else {
                    try {
                        StoreUser::make()->action(
                            parent: $employee,
                            modelData: $employeeData['user'],
                            hydratorsDelay: 60,
                            strict: false,
                        );
                    } catch (Exception|Throwable $e) {
                        $this->recordError($organisationSource, $e, $employeeData['user'], 'User', 'store');

                        return null;
                    }
                }
            }

            return $employee;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('wowsbar')
            ->table('employees')
            ->select('id as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('wowsbar')->table('employees')
            ->count();
    }
}
