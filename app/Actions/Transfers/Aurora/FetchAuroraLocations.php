<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 01:11:27 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Models\Inventory\Location;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraLocations extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:locations {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Location
    {
        if ($locationData = $organisationSource->fetchLocation($organisationSourceId)) {
            if ($location = Location::withTrashed()->where('source_id', $locationData['location']['source_id'])
                ->first()) {
                try {
                    $location = UpdateLocation::make()->action(
                        location: $location,
                        modelData: $locationData['location'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $location->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $locationData['location'], 'Location', 'update');

                    return null;
                }
            } else {
                try {

                    $location = StoreLocation::make()->action(
                        parent: $locationData['parent'],
                        modelData: $locationData['location'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Location::enableAuditing();
                    $this->saveMigrationHistory(
                        $location,
                        Arr::except($locationData['location'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $location->source_id);
                    DB::connection('aurora')->table('Location Dimension')
                        ->where('Location Key', $sourceData[1])
                        ->update(['aiku_id' => $location->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $locationData['location'], 'Location', 'store');

                    return null;
                }
            }

            return $location;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Location Dimension')
            ->select('Location Key as source_id')
            ->orderBy('source_id');

    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Location Dimension')->count();
    }
}
