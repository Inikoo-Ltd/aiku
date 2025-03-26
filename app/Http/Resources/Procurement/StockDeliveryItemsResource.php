<?php
/*
 * author Arya Permana - Kirin
 * created on 26-03-2025-15h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

class StockDeliveryItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'state'    => $this->state,
            'net_amount'    => $this->net_amount,
            'gross_amount'    => $this->gross_amount,
            'unit_quantity'    => $this->unit_quantity,
            'unit_quantity_checked'    => $this->unit_quantity_checked,
            'unit_quantity_placed'    => $this->unit_quantity_placed,
            'net_unit_price'    => $this->net_unit_price,
            'gross_unit_price'    => $this->gross_unit_price,
            'supplier_product_code'    => $this->supplier_product_code,
            'supplier_product_name'    => $this->supplier_product_name,
            'supplier_product_cost'    => $this->supplier_product_cost,
        ];
    }
}
