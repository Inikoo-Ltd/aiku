<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 12:27:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraShippingZoneSchema extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Shipping Zone Schema Store Key'});

        $isCurrent = false;
        $isCurrentDiscount = false;
        $state = ShippingZoneSchemaStateEnum::DECOMMISSIONED;
        if ($this->auroraModelData->{'Shipping Zone Schema Type'} == 'Current') {
            $isCurrent = true;
            $state = ShippingZoneSchemaStateEnum::LIVE;
        } elseif ($this->auroraModelData->{'Shipping Zone Schema Type'} == 'Deal') {
            $isCurrentDiscount = true;
            $state = ShippingZoneSchemaStateEnum::LIVE;
        }

        $this->parsedData['shop'] = $shop;
        $this->parsedData['shipping-zone-schema'] = [
            'state' => $state,
            'is_current' => $isCurrent,
            'is_current_discount' => $isCurrentDiscount,
            'name' => $this->auroraModelData->{'Shipping Zone Schema Label'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Shipping Zone Schema Key'},

        ];

        $createdBy = $this->auroraModelData->{'Shipping Zone Schema Creation Date'};

        if ($createdBy) {
            $this->parsedData['shipping-zone-schema']['created_by'] = $createdBy;
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Shipping Zone Schema Dimension')
            ->where('Shipping Zone Schema Key', $id)->first();
    }
}
