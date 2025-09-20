<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 03:13:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\OrgStock\SyncOrgStockLocations;
use App\Models\Inventory\OrgStock;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraStockLocations extends FetchAuroraAction
{
    use WithAuroraAttachments;
    use HasStockLocationsFetch;
    use WithFetchStock;


    public string $commandSignature = 'fetch:stock_locations {organisations?*} {--s|source_id=}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): array
    {


        $orgStock = OrgStock::where('source_id', $organisationSource->getOrganisation()->id.':'.$organisationSourceId)->first();


        if ($orgStock) {
            $locationsData = $this->getStockLocationData($organisationSource, $organisationSource->getOrganisation()->id.':'.$organisationSourceId);
            SyncOrgStockLocations::make()->action($orgStock, [
                'locationsData' => $locationsData
            ], 2, false);
        }

        return [];

    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Part Dimension')
            ->select('Part SKU as source_id');


        $query->orderBy('Part Valid From');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Part Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }


}
