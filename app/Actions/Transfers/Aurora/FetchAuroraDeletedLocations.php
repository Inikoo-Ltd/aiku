<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Feb 2023 09:53:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Location\UpdateLocation;
use App\Models\Inventory\Location;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeletedLocations extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:deleted-locations {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Location
    {
        if ($deletedLocationData = $organisationSource->fetchDeletedLocation($organisationSourceId)) {
            if ($location = Location::withTrashed()->where('source_id', $deletedLocationData['location']['source_id'])
                ->first()) {
                $location = UpdateLocation::make()->action(
                    location: $location,
                    modelData: $deletedLocationData['location'],
                    hydratorsDelay: 60,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $location->wasChanged());
            } else {
                $location = StoreLocation::make()->action(
                    parent: $deletedLocationData['parent'],
                    modelData: $deletedLocationData['location'],
                    hydratorsDelay: 60,
                    strict: false,
                );

                $this->recordNew($organisationSource);
                $sourceData = explode(':', $location->source_id);
                DB::connection('aurora')->table('Location Deleted Dimension')
                    ->where('Location Deleted Key', $sourceData[1])
                    ->update(['aiku_id' => $location->id]);

            }

            return $location;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Location Deleted Dimension')
            ->select('Location Deleted Key as source_id')
            ->orderBy('source_id')
            ->when(app()->environment('testing'), function ($query) {
                return $query->limit(20);
            });
    }


    public function count(): ?int
    {
        return DB::connection('aurora')->table('Location Deleted Dimension')->count();
    }
}
