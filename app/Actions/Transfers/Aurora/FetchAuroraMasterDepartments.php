<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:51:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraMasterDepartments extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:master_departments {organisations?*}  {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?MasterProductCategory
    {
        if ($masterDepartmentData = $organisationSource->fetchMasterDepartment($organisationSourceId)) {
            if ($masterDepartment = MasterProductCategory::where('source_department_id', $masterDepartmentData['master_department']['source_department_id'])
                ->first()) {
                try {
                    $masterDepartment = UpdateMasterProductCategory::make()->action(
                        masterProductCategory: $masterDepartment,
                        modelData: $masterDepartmentData['master_department'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $masterDepartment->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $masterDepartmentData['master_department'], 'MasterDepartment', 'update');

                    return null;
                }
            } else {
                try {
                    $masterDepartment = StoreMasterProductCategory::make()->action(
                        parent: $masterDepartmentData['master_shop'],
                        modelData: $masterDepartmentData['master_department'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false
                    );
                    MasterProductCategory::enableAuditing();
                    $this->saveMigrationHistory(
                        $masterDepartment,
                        Arr::except($masterDepartmentData['master_department'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $masterDepartmentData['master_department'], 'MasterDepartment', 'store');

                    return null;
                }
            }


            return $masterDepartment;
        }


        return null;
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->select('Category Key as source_id');
        $query = $this->commonSelectModelsToFetch($query);


        return $query->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Category Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        $departmentSourceIDs = [];
        $preQuery            = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Department Category Key');
        foreach ($preQuery->get() as $row) {
            $departmentSourceIDs[] = $row->{'Store Department Category Key'};
        }
        $query->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $departmentSourceIDs);

        if ($this->onlyNew) {
            $query->whereNull('aiku_department_id');
        }

        return $query;
    }


}
