<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 27 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Procurement;

use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDeliveryUnderOverDeliveredItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;

        return [
            'id'                    => $item->id,
            'code'                  => $supplierProduct?->code,
            'name'                  => $supplierProduct?->name,
            'units_per_pack'        => $supplierProduct?->units_per_pack,
            'units_per_carton'      => $supplierProduct?->units_per_carton,
            'unit_quantity'         => $item->unit_quantity,
            'unit_quantity_checked' => $item->unit_quantity_checked,
            'org_stock_id'          => $item->org_stock_id,
            'org_stock_slug'        => $item->org_stock_slug,
            'org_stock_code'        => $item->org_stock_code,
            'org_stock_name'        => $item->org_stock_name,
            'difference_units'      => (float) $item->difference_units,
            'difference_skos'       => $item->difference_skos === null ? null : (float) $item->difference_skos,
            'difference_percentage' => $item->difference_percentage === null ? null : (float) $item->difference_percentage,
        ];
    }
}
