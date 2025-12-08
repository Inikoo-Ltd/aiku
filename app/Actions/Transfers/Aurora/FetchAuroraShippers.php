<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 02:12:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\Dispatching\Shipper\UpdateShipper;
use App\Models\Dispatching\Shipper;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraShippers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:shippers {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Shipper
    {
        if ($shipperData = $organisationSource->fetchShipper($organisationSourceId)) {
            if ($shipper = Shipper::where('source_id', $shipperData['shipper']['source_id'])
                ->first()) {

                $shipper = UpdateShipper::make()->action(
                    shipper: $shipper,
                    modelData: $shipperData['shipper'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $shipper->wasChanged());
            } else {
                try {
                    $shipper = StoreShipper::make()->action(
                        organisation: $organisationSource->getOrganisation(),
                        modelData: $shipperData['shipper'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Shipper::enableAuditing();
                    $this->saveMigrationHistory(
                        $shipper,
                        Arr::except($shipperData['shipper'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $shipper->source_id);
                    DB::connection('aurora')->table('Shipper Dimension')
                        ->where('Shipper Key', $sourceData[1])
                        ->update(['aiku_id' => $shipper->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $shipperData['shipper'], 'Shipper', 'store');

                    return null;
                }

            }

            return $shipper;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Shipper Dimension')
            ->select('Shipper Key as source_id')
            ->where('Shipper Active', 'Yes')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Shipper Dimension')
            ->where('Shipper Active', 'Yes')
            ->count();
    }
}
