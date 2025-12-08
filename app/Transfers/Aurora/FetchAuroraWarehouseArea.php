<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraWarehouseArea extends FetchAurora
{
    protected function parseModel(): void
    {
        $code = strtolower($this->auroraModelData->{'Warehouse Area Code'});

        $code = preg_replace('/\s*warehouse$/i', '', $code);

        $this->parsedData['warehouse'] = $this->parseWarehouse($this->organisation->id.':'.$this->auroraModelData->{'Warehouse Area Warehouse Key'});
        $this->parsedData['warehouse_area'] = [
            'name' => $this->auroraModelData->{'Warehouse Area Name'} ?? 'Name not set',
            'code' => Str::kebab($code),
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Warehouse Area Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Warehouse Area Dimension')
            ->where('Warehouse Area Key', $id)->first();
    }
}
