<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationStockHistoriesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'bucket'                         => $this->bucket,
            'org_stock_value'                => $this->org_stock_value,
            'grp_stock_value'                => $this->grp_stock_value,
            'number_org_stocks'              => $this->number_org_stocks,
            'number_out_of_stock_org_stocks' => $this->number_out_of_stock_org_stocks,
            'number_location_org_stocks'     => $this->number_location_org_stocks,
            'org_currency_code'              => $this->org_currency_code,
            'grp_currency_code'              => $this->grp_currency_code,
        ];
    }
}
