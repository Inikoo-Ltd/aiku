<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $date
 * @property mixed $class
 * @property mixed $type
 * @property mixed $flow
 * @property mixed $quantity
 * @property mixed $org_stock_name
 * @property mixed $org_stock_slug
 * @property mixed $org_amount
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $warehouse_name
 * @property mixed $warehouse_slug
 * @property mixed $location_code
 * @property mixed $location_slug
 * @property mixed $operation_type
 * @property mixed $operation_id
 * @property mixed $currency_code
 */
class OrgStockMovementsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'date'              => $this->date,
            'class'             => $this->class,
            'type'              => $this->type,
            'flow'              => $this->flow,
            'quantity'          => $this->quantity,
            'org_stock_name'    => $this->org_stock_name,
            'org_stock_slug'    => $this->org_stock_slug,
            'org_amount'        => $this->org_amount,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'warehouse_name'    => $this->warehouse_name,
            'warehouse_slug'    => $this->warehouse_slug,
            'location_code'     => $this->location_code,
            'location_slug'     => $this->location_slug,
            'operation_type'    => $this->operation_type,
            'operation_id'      => $this->operation_id,
            'currency_code'     => $this->currency_code
        ];
    }
}
