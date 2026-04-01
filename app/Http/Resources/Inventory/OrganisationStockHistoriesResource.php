<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $bucket
 * @property mixed $org_stock_value
 * @property mixed $grp_stock_value
 * @property mixed $org_stock_commercial_value
 * @property mixed $grp_stock_commercial_value
 * @property mixed $number_org_stocks
 * @property mixed $number_out_of_stock_org_stocks
 * @property mixed $number_location_org_stocks
 * @property mixed $org_currency_code
 * @property mixed $grp_currency_code
 * @property mixed $number_locations
 * @property mixed $number_org_stocks_not_sold_1y
 * @property mixed $value_dormant_stock_1y
 */
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
            'number_locations'               => $this->number_locations,
            'number_org_stocks_not_sold_1y'  => $this->number_org_stocks_not_sold_1y,
            'value_dormant_stock_1y'         => $this->value_dormant_stock_1y
        ];
    }
}
