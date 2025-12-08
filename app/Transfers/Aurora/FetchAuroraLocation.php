<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraWarehouseAreas;
use App\Actions\Transfers\Aurora\FetchAuroraWarehouses;
use Illuminate\Support\Facades\DB;

class FetchAuroraLocation extends FetchAurora
{
    protected function parseModel(): void
    {
        $parent = null;

        if (is_numeric($this->auroraModelData->{'Location Warehouse Area Key'})) {
            $parent = FetchAuroraWarehouseAreas::run($this->organisationSource, $this->auroraModelData->{'Location Warehouse Area Key'});
        }
        if (! $parent) {
            $parent = FetchAuroraWarehouses::run($this->organisationSource, $this->auroraModelData->{'Location Warehouse Key'});
        }

        $code = $this->auroraModelData->{'Location Code'};
        $code = str_replace(' ', '-', $code);
        $code = str_replace('A&C', 'AC', $code);
        $code = str_replace('.', '-', $code);
        $code = str_replace('+', '-', $code);
        $code = str_replace('*', '', $code);
        $code = str_replace('/', '', $code);
        if ($code == 'Papier.-Lep.-Pás') {
            $code = 'Papier-Lep-Pas';
        }
        if ($code == 'Affinity-(Goods-') {
            $code = 'Affinity-Goods2';
        }

        $this->parsedData['parent'] = $parent;
        $this->parsedData['location'] = [
            'code' => $code,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Location Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'max_weight' => $this->auroraModelData->{'Location Max Weight'} == 0 ? null : $this->auroraModelData->{'Location Max Weight'},
            'max_volume' => $this->auroraModelData->{'Location Max Volume'} == 0 ? null : $this->auroraModelData->{'Location Max Volume'},
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Location Dimension')
            ->where('Location Key', $id)->first();
    }
}
