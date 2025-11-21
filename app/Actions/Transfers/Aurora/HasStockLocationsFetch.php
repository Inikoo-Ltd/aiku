<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 10:09:41 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Support\Facades\DB;

trait HasStockLocationsFetch
{
    use WithAuroraParsers;

    public function getStockLocationData($organisationSource, $sourceId): array
    {
        $organisation = $organisationSource->getOrganisation();
        $sourceData   = explode(':', $sourceId);


        $stockLocations  = [];
        $auroraModelData = DB::connection('aurora')
            ->table('Part Location Dimension')
            ->where('Part SKU', $sourceData[1])
            ->orderBy('Can Pick')
            ->get();

        $pickingPriority = null;
        foreach ($auroraModelData as $modelData) {
            $location = $this->parseLocation($sourceData[0].':'.$modelData->{'Location Key'}, $organisationSource);

            if (!$location) {
                $this->recordFetchError(
                    $organisationSource,
                    $auroraModelData,
                    'Stock Location',
                    'fetching',
                    [
                        'msg' => 'Location not found',
                    ]
                );
                continue;
            }


            $settings = [];
            if ($modelData->{'Minimum Quantity'}) {
                $settings['min_stock'] = $modelData->{'Minimum Quantity'};
            }
            if ($modelData->{'Maximum Quantity'}) {
                $settings['max_stock'] = $modelData->{'Maximum Quantity'};
            }
            if ($modelData->{'Moving Quantity'}) {
                $settings['max_stock'] = $modelData->{'Moving Quantity'};
            }


            $type = LocationStockTypeEnum::STORING->value;
            if ($modelData->{'Can Pick'} == 'Yes') {
                $type = LocationStockTypeEnum::PICKING->value;
            }
            $pickingPriority = $pickingPriority === null ? 1 : $pickingPriority + 1;


            $stockLocations[$location->id] = [
                'quantity'           => round($modelData->{'Quantity On Hand'}, 3),
                'audited_at'         => $modelData->{'Part Location Last Audit'},
                'notes'              => $modelData->{'Part Location Note'},
                'data'               => [],
                'settings'           => $settings,
                'source_stock_id'    => $organisation->id.':'.$modelData->{'Part SKU'},
                'source_location_id' => $organisation->id.':'.$modelData->{'Location Key'},
                'picking_priority'   => $pickingPriority,
                'type'               => $type,
                'fetched_at'         => now(),
                'last_fetched_at'    => now()
            ];
        }

        return $stockLocations;
    }

    public function getStockData($sourceId): \Illuminate\Support\Collection
    {
        $sourceData = explode(':', $sourceId);


        return DB::connection('aurora')
            ->table('Part Dimension')
            ->select('Part Current Stock Ordered Paid', 'Part Current Stock In Process', 'Part On Demand')
            ->where('Part SKU', $sourceData[1])
            ->get();
    }

}
