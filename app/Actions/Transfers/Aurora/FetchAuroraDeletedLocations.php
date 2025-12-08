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
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeletedLocations extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:deleted_locations {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Location
    {
        if ($deletedLocationData = $organisationSource->fetchDeletedLocation($organisationSourceId)) {
            if ($location = Location::withTrashed()->where('source_id', $deletedLocationData['location']['source_id'])
                ->first()) {
                try {
                    $location = UpdateLocation::make()->action(
                        location: $location,
                        modelData: $deletedLocationData['location'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $location->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $deletedLocationData['location'], 'Location', 'update');

                    return null;
                }
            } else {
                try {
                    $location = StoreLocation::make()->action(
                        parent: $deletedLocationData['parent'],
                        modelData: $deletedLocationData['location'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Location::enableAuditing();
                    $this->saveMigrationHistory(
                        $location,
                        Arr::except($deletedLocationData['location'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $location->source_id);
                    DB::connection('aurora')->table('Location Deleted Dimension')
                        ->where('Location Deleted Key', $sourceData[1])
                        ->update(['aiku_id' => $location->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $deletedLocationData['location'], 'Location', 'store');

                    return null;
                }
            }

            return $location;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Location Deleted Dimension')
            ->select('Location Deleted Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        $query->orderBy('source_id')
            ->when(app()->environment('testing'), function ($query) {
                return $query->limit(20);
            });

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Location Deleted Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
