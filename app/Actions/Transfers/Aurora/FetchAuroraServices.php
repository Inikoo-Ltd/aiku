<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 06 Dec 2022 17:28:37 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Billables\Rental\StoreRental;
use App\Actions\Billables\Rental\UpdateRental;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Billables\Service\UpdateService;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraServices extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:services {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): Service|Rental|null
    {

        if ($serviceData = $organisationSource->fetchService($organisationSourceId)) {

            if ($serviceData['type'] == AssetTypeEnum::SERVICE) {

                if ($service = Service::where('source_id', $serviceData['service']['source_id'])
                    ->first()) {
                    $service = UpdateService::make()->action(
                        service: $service,
                        modelData: $serviceData['service'],
                        hydratorsDelay: $this->hydratorsDelay,
                    );
                } else {

                    try {

                        $service = StoreService::make()->action(
                            shop: $serviceData['shop'],
                            modelData: $serviceData['service'],
                            hydratorsDelay: $this->hydratorsDelay,
                        );
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $serviceData['service'], 'Asset', 'store');

                        return null;
                    }
                }
                $sourceData = explode(':', $service->source_id);

                DB::connection('aurora')->table('Product Dimension')
                    ->where('Product ID', $sourceData[1])
                    ->update(['aiku_id' => $service->asset->id]);

                return $service;

            } else {

                if ($rental = Rental::where('source_id', $serviceData['service']['source_id'])
                    ->first()) {
                    $rental = UpdateRental::make()->action(
                        rental: $rental,
                        modelData: $serviceData['service'],
                        hydratorsDelay: $this->hydratorsDelay,
                    );
                } else {
                    try {

                        $rental = StoreRental::make()->action(
                            shop: $serviceData['shop'],
                            modelData: $serviceData['service'],
                            hydratorsDelay: $this->hydratorsDelay,
                        );
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $serviceData['service'], 'Asset', 'store');

                        return null;
                    }
                }
                $sourceData = explode(':', $rental->source_id);

                DB::connection('aurora')->table('Product Dimension')
                    ->where('Product ID', $sourceData[1])
                    ->update(['aiku_id' => $rental->asset_id]);

                return $rental;

            }

        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Product Dimension')
            ->where('Product Type', 'Service')
            ->select('Product ID as source_id')
            ->orderBy('Product ID');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Product Dimension')
            ->where('Product Type', 'Service');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        return $query->count();
    }
}
