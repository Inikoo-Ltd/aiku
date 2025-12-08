<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Feb 2023 12:33:39 Malaysia Time, Ubud, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Models\HumanResources\Employee;
use App\Transfers\SourceOrganisationService;
use Arr;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeletedEmployees extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:deleted_employees {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Employee
    {
        if ($employeeData = $organisationSource->fetchDeletedEmployee($organisationSourceId)) {
            if ($employee = Employee::withTrashed()->where('source_id', $employeeData['employee']['source_id'])->first()) {
                try {
                    $employee = UpdateEmployee::make()->action(
                        employee: $employee,
                        modelData: $employeeData['employee'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $employee->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $employeeData['employee'], 'DeletedEmployee', 'update');

                    return null;
                }
            } else {
                $workplace = $organisationSource->getOrganisation()->workplaces()->first();
                try {
                    $employee = StoreEmployee::make()->action(
                        parent: $workplace,
                        modelData: $employeeData['employee'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Employee::enableAuditing();
                    $this->saveMigrationHistory(
                        $employee,
                        Arr::except($employeeData['employee'], ['fetched_at', 'last_fetched_at', 'source_id', 'positions', 'user_model_status'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $employee->source_id);
                    DB::connection('aurora')->table('Staff Deleted Dimension')
                        ->where('Staff Deleted Key', $sourceData[1])
                        ->update(['aiku_id' => $employee->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $employeeData['employee'], 'DeletedEmployee', 'store');

                    return null;
                }
            }

            return $employee;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Staff Deleted Dimension')
            ->select('Staff Deleted Key as source_id')
            ->where('Staff Deleted Type', '!=', 'Contractor')
            ->orderBy('source_id')
            ->when(app()->environment('testing'), function ($query) {
                return $query->limit(20);
            });
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Staff Deleted Dimension')
            ->where('Staff Deleted Type', '!=', 'Contractor')
            ->count();
    }
}
