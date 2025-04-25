<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 00:15:06 Malaysia Time, Kuala Lumpur, Malaysia
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

class FetchAuroraMasterFamilies extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:master_families {organisations?*}  {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?MasterProductCategory
    {
        if ($masterFamilyData = $organisationSource->fetchMasterFamily($organisationSourceId)) {
            if ($masterFamily = MasterProductCategory::where('source_family_id', $masterFamilyData['master_family']['source_family_id'])
                ->first()) {
                try {
                    $masterFamily = UpdateMasterProductCategory::make()->action(
                        masterProductCategory: $masterFamily,
                        modelData: $masterFamilyData['master_family'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $masterFamily->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $masterFamilyData['master_family'], 'MasterFamily', 'update');

                    return null;
                }
            } else {
                try {
                    $masterFamily = StoreMasterProductCategory::make()->action(
                        parent: $masterFamilyData['parent'],
                        modelData: $masterFamilyData['master_family'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false
                    );
                    MasterProductCategory::enableAuditing();
                    $this->saveMigrationHistory(
                        $masterFamily,
                        Arr::except($masterFamilyData['master_family'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $masterFamilyData['master_family'], 'MasterFamily', 'store');

                    return null;
                }
            }


            return $masterFamily;
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
        $familySourceIDs = [];
        $preQuery        = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Family Category Key');
        foreach ($preQuery->get() as $row) {
            $familySourceIDs[] = $row->{'Store Family Category Key'};
        }
        $query->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $familySourceIDs);


        return $query;
    }


}
