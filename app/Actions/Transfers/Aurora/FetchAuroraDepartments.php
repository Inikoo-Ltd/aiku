<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 08:14:07 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Models\Catalogue\ProductCategory;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDepartments extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:departments {organisations?*} {--N|only_new : Fetch only new}  {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ProductCategory
    {
        if ($departmentData = $organisationSource->fetchDepartment($organisationSourceId)) {
            if ($department = ProductCategory::where('source_department_id', $departmentData['department']['source_department_id'])
                ->first()) {
                try {
                    $department = UpdateProductCategory::make()->action(
                        productCategory: $department,
                        modelData: $departmentData['department'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $department->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $departmentData['department'], 'Department', 'update');

                    return null;
                }
            } else {
                try {
                    $department = StoreProductCategory::make()->action(
                        parent: $departmentData['shop'],
                        modelData: $departmentData['department'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false
                    );
                    ProductCategory::enableAuditing();
                    $this->saveMigrationHistory(
                        $department,
                        Arr::except($departmentData['department'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $department->source_department_id);

                    DB::connection('aurora')->table('Category Dimension')
                        ->where('Category Key', $sourceData[1])
                        ->update(['aiku_department_id' => $department->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $departmentData['department'], 'Department', 'store');

                    return null;
                }
            }


            return $department;
        }


        return null;
    }


    public function getModelsQuery(): Builder
    {
        $departmentSourceIDs = [];
        $query               = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Department Category Key');
        foreach ($query->get() as $row) {
            $departmentSourceIDs[] = $row->{'Store Department Category Key'};
        }
        if ($this->onlyNew) {
            $query->whereNull('aiku_department_id');
        }

        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->select('Category Key as source_id')
            ->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $departmentSourceIDs);


        return $query->orderBy('source_id');
    }

    public function count(): ?int
    {
        $departmentSourceIDs = [];
        $query               = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Department Category Key');
        foreach ($query->get() as $row) {
            $departmentSourceIDs[] = $row->{'Store Department Category Key'};
        }


        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $departmentSourceIDs);

        if ($this->onlyNew) {
            $query->whereNull('aiku_department_id');
        }

        return $query->count();
    }


}
