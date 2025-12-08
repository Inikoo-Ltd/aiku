<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraHistoricService extends FetchAurora
{
    protected function parseModel(): void
    {
        $this->parsedData['service'] = $this->parseService($this->organisation->id.':'.$this->auroraModelData->{'Product ID'});

        $deleted_at = $this->parseDatetime($this->auroraModelData->{'Product History Valid To'});

        $status = 0;
        if (DB::connection('aurora')->table('Product Dimension')->where('Product Current Key', '=', $this->auroraModelData->{'Product Key'})->exists()) {
            $status = 1;
            $deleted_at = null;
        }

        $this->parsedData['historic_service'] = [
            'code' => $this->auroraModelData->{'Product History Code'},
            'name' => $this->auroraModelData->{'Product History Name'},
            'price' => $this->auroraModelData->{'Product History Price'},
            'status' => $status,
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Product History Valid From'}),
            'deleted_at' => $deleted_at,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Product Key'},
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Product History Dimension')
            ->where('Product Key', $id)->first();
    }
}
