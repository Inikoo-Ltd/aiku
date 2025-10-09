<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 12:26:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZone\UpdateShippingZone;
use App\Models\Billables\ShippingZone;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraShippingZones extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:shipping_zones {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ShippingZone
    {
        if ($shippingZoneData = $organisationSource->fetchShippingZone($organisationSourceId)) {
            if ($shippingZone = ShippingZone::where('source_id', $shippingZoneData['shipping-zone']['source_id'])->first()) {
                try {
                    $shippingZone = UpdateShippingZone::make()->action(
                        shippingZone: $shippingZone,
                        modelData: $shippingZoneData['shipping-zone'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $shippingZone->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $shippingZoneData['shipping-zone'], 'ShippingZone', 'update');
                    return null;
                }
            } else {
                try {
                    $shippingZone = StoreShippingZone::make()->action(
                        shippingZoneSchema: $shippingZoneData['shipping-zone-schema'],
                        modelData: $shippingZoneData['shipping-zone'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    ShippingZone::enableAuditing();
                    $this->saveMigrationHistory(
                        $shippingZone,
                        Arr::except($shippingZoneData['shipping-zone'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $shippingZone->source_id);
                    DB::connection('aurora')->table('Shipping Zone Dimension')
                        ->where('Shipping Zone Key', $sourceData[1])
                        ->update(['aiku_id' => $shippingZone->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $shippingZoneData['shipping-zone'], 'ShippingZone', 'store');
                    return null;
                }
            }

            return $shippingZone;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Shipping Zone Dimension')
            ->select('Shipping Zone Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Shipping Zone Dimension')->count();
    }


}
