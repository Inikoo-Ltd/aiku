<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 01:31:39 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Inventory\Warehouse\UpdateWarehouse;
use App\Models\Inventory\Warehouse;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraWarehouses extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:warehouses {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Warehouse
    {
        setPermissionsTeamId($organisationSource->getOrganisation()->group_id);
        if ($warehouseData = $organisationSource->fetchWarehouse($organisationSourceId)) {
            if ($warehouse = Warehouse::where('source_id', $warehouseData['warehouse']['source_id'])
                ->first()) {
                try {
                    $warehouse = UpdateWarehouse::make()->action(
                        warehouse: $warehouse,
                        modelData: $warehouseData['warehouse'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $warehouseData['warehouse'], 'Warehouse', 'update');

                    return null;
                }
            } else {
                try {
                    $warehouse = StoreWarehouse::make()->action(
                        organisation: $organisationSource->getOrganisation(),
                        modelData: $warehouseData['warehouse'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Warehouse::enableAuditing();

                    $this->saveMigrationHistory(
                        $warehouse,
                        Arr::except($warehouseData['warehouse'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $warehouse->source_id);
                    DB::connection('aurora')->table('Warehouse Dimension')
                        ->where('Warehouse Key', $sourceData[1])
                        ->update(['aiku_id' => $warehouse->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $warehouseData['warehouse'], 'Warehouse', 'store');

                    return null;
                }
            }

            return $warehouse;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Warehouse Dimension')
            ->select('Warehouse Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Warehouse Dimension')->count();
    }
}
