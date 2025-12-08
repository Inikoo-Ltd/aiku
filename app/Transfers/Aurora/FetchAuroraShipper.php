<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraShipper extends FetchAurora
{
    protected function parseModel(): void
    {
        $code = strtolower($this->auroraModelData->{'Shipper Code'});
        $code = str_replace(' ', '-', $code);

        if ($this->organisation->slug == 'sk') {
            if ($code == 'dpd') {
                $code = 'sk-dpd2';
            }
            if (in_array($code, ['tnt', 'dhl', 'kuehnenagel', 'gls', 'ups'])) {
                $code = 'sk-'.$code;
            }
        }

        if ($this->organisation->slug == 'es') {
            if (in_array($code, ['gls', 'tnt', 'dhl', 'ups'])) {
                $code = 'es-'.$code;
            }
        }

        if ($this->organisation->slug == 'aroma') {
            if (in_array($code, ['apc', 'gls', 'tnt', 'dhl', 'ups', 'dpd', 'simarco'])) {
                $code = 'aro-'.$code;
            }
        }

        $this->parsedData['shipper'] = [
            'code' => $code,
            'name' => $this->auroraModelData->{'Shipper Name'},
            'website' => $this->auroraModelData->{'Shipper Website'},
            'company_name' => $this->auroraModelData->{'Shipper Fiscal Name'},
            'contact_name' => $this->auroraModelData->{'Shipper Name'},
            'phone' => $this->auroraModelData->{'Shipper Telephone'},
            'status' => $this->auroraModelData->{'Shipper Active'} === 'Yes',
            'tracking_url' => $this->auroraModelData->{'Shipper Tracking URL'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Shipper Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Shipper Dimension')
            ->where('Shipper Key', $id)->first();
    }
}
