<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraWarehouses;
use App\Actions\Transfers\Aurora\FetchAuroraWarehouseAreas;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

class FetchAuroraLocation extends FetchAurora
{
    protected function parseModel(): void
    {
        $parent = null;

        if (is_numeric($this->auroraModelData->{'Location Warehouse Area Key'})) {
            $parent = FetchAuroraWarehouseAreas::run($this->organisationSource, $this->auroraModelData->{'Location Warehouse Area Key'});
        }
        if (!$parent) {
            $parent = FetchAuroraWarehouses::run($this->organisationSource, $this->auroraModelData->{'Location Warehouse Key'});
        }

        $code = self::normaliseCode($this->auroraModelData->{'Location Code'});

        $this->parsedData['parent']   = $parent;
        $this->parsedData['location'] = [
            'code'            => $code,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Location Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'max_weight'      => $this->auroraModelData->{'Location Max Weight'} == 0 ? null : $this->auroraModelData->{'Location Max Weight'},
            'max_volume'      => $this->auroraModelData->{'Location Max Volume'} == 0 ? null : $this->auroraModelData->{'Location Max Volume'},
        ];
    }


    public static function normaliseCode(string $code): string
    {
        $code = str_replace([' ', '.', '+'], '-', $code);
        $code = str_replace(['A&C', '*', '/'], ['AC', '', ''], $code);

        return match ($code) {
            'Papier.-Lep.-Pás' => 'Papier-Lep-Pas',
            'Affinity-(Goods-' => 'Affinity-Goods2',
            default => $code
        };
    }

    public static function findAikuLocation(int $organisationId, object $auroraLocation): ?Location
    {
        $warehouse = Warehouse::where('source_id', $organisationId.':'.$auroraLocation->{'Location Warehouse Key'})->first();
        if (!$warehouse) {
            return null;
        }

        return Location::where('warehouse_id', $warehouse->id)
            ->where('code', self::normaliseCode($auroraLocation->{'Location Code'}))
            ->first();
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Location Dimension')
            ->where('Location Key', $id)->first();
    }
}
