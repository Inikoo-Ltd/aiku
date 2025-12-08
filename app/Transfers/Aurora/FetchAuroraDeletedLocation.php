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

class FetchAuroraDeletedLocation extends FetchAurora
{
    protected function parseModel(): void
    {
        $parent = null;

        $deleted_at = $this->auroraModelData->{'Location Deleted Date'};
        if (! $deleted_at) {
            return;
        }

        if (is_numeric($this->auroraModelData->{'Location Deleted Warehouse Area Key'})) {
            $parent = FetchAuroraWarehouseAreas::run($this->organisationSource, $this->auroraModelData->{'Location Deleted Warehouse Area Key'});
        }
        if (! $parent) {
            $parent = FetchAuroraWarehouses::run($this->organisationSource, $this->auroraModelData->{'Location Deleted Warehouse Key'});
        }
        $code = $this->auroraModelData->{'Location Deleted Code'};
        $code = str_replace(' ', '-', $code);
        $code = str_replace('A&C', 'AC', $code);
        $code = str_replace('.', '-', $code);
        $code = str_replace('+', '-', $code);
        $code = str_replace('*', '', $code);
        $code = str_replace('/', '', $code);

        $code = $code.'-deleted-'.$this->auroraModelData->{'Location Deleted Key'};

        $this->parsedData['parent'] = $parent;
        $this->parsedData['location'] = [
            'code' => $code,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Location Deleted Key'},
            'deleted_at' => $deleted_at,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Location Deleted Dimension')
            ->where('Location Deleted Key', $id)->first();
    }
}
