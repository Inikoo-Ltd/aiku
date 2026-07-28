<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Procurement;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDeliveryItemCostResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;
        $stockDelivery   = $item->stockDelivery;
        $isEditable      = $stockDelivery?->state === StockDeliveryStateEnum::BOOKED_IN && $stockDelivery?->is_costed;

        return [
            'id'                   => $item->id,
            'name'                 => $supplierProduct?->name,
            'units_per_pack'       => $supplierProduct?->units_per_pack,
            'units_per_carton'     => $supplierProduct?->units_per_carton,
            'unit_quantity_placed' => $item->unit_quantity_placed,
            'org_stock_id'         => $item->org_stock_id,
            'org_stock_code'       => $item->org_stock_code,
            'currency'             => $stockDelivery?->currency?->code,
            'cost_items'           => $item->cost_items,
            'cost_extra'           => $item->cost_extra,
            'cost_shipping'        => $item->cost_shipping,
            'cost_duties'          => $item->cost_duties,
            'cost_tax'             => $item->cost_tax,
            'cost_total'           => $item->cost_total,
            'updateCostRoute'      => $isEditable ? [
                'name'       => 'grp.models.stock-delivery-item.update-cost',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
        ];
    }
}
