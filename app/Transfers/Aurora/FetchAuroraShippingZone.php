<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 12:27:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraShippingZone extends FetchAurora
{
    protected function parseModel(): void
    {
        $shippingZoneSchema = $this->parseShippingZoneSchema($this->organisation->id.':'.$this->auroraModelData->{'Shipping Zone Shipping Zone Schema Key'});


        if ($shippingZoneSchema->shop->type == ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $this->parsedData['shipping-zone-schema'] = $shippingZoneSchema;
        $this->parsedData['shipping-zone']        = [
            'is_failover'     => $this->auroraModelData->{'Shipping Zone Type'}   == 'Failover',
            'status'          => $this->auroraModelData->{'Shipping Zone Active'} == 'Yes',
            'code'            => $this->auroraModelData->{'Shipping Zone Code'},
            'name'            => $this->auroraModelData->{'Shipping Zone Name'},
            'price'           => json_decode($this->auroraModelData->{'Shipping Zone Price'}, true),
            'position'        => $this->auroraModelData->{'Shipping Zone Position'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Shipping Zone Key'},
        ];

        $territories = $this->auroraModelData->{'Shipping Zone Territories'};
        if ($territories) {
            $this->parsedData['shipping-zone']['territories'] = json_decode($territories, true);
        }


        // dd( $this->parsedData['shipping-zone']);

        $createdBy = $this->auroraModelData->{'Shipping Zone Creation Date'};

        if ($createdBy) {
            $this->parsedData['shipping-zone']['created_by'] = $createdBy;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Shipping Zone Dimension')
            ->where('Shipping Zone Key', $id)->first();
    }
}
