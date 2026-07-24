<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;

        return [
            'id'                    => $item->id,
            'slug'                  => $supplierProduct?->slug,
            'code'                  => $supplierProduct?->code,
            'name'                  => $supplierProduct?->name,
            'units_per_pack'        => $supplierProduct?->units_per_pack,
            'units_per_carton'      => $supplierProduct?->units_per_carton,
            'unit_quantity'         => $item->unit_quantity,
            'unit_quantity_checked' => $item->unit_quantity_checked,
            'unit_quantity_placed'  => $item->unit_quantity_placed,
            'net_amount'            => $item->net_amount,
            'net_currency'          => $supplierProduct?->currency?->code,
            'org_net_amount'        => $item->org_net_amount,
            'org_currency'          => $item->organisation?->currency?->code,
            'org_exchange'          => $item->org_exchange,
            'weight'                => $item->weight === null ? null : (float) $item->weight,
            'volume'                => $item->volume === null ? null : (float) $item->volume,
            'state'                 => $item->state->value,
            'state_label'           => StockDeliveryItemStateEnum::labels()[$item->state->value],
            'state_icon'            => StockDeliveryItemStateEnum::stateIcon()[$item->state->value],
            'org_stock_id'          => $item->org_stock_id,
            'org_stock_slug'        => $item->org_stock_slug,
            'org_stock_code'        => $item->org_stock_code,
            'org_stock_name'        => $item->org_stock_name,
            'confirmRoute'          => $item->state === StockDeliveryItemStateEnum::IN_PROCESS ? [
                'name'       => 'grp.models.stock-delivery-item.confirm',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'readyToShipRoute'      => $item->state === StockDeliveryItemStateEnum::CONFIRMED ? [
                'name'       => 'grp.models.stock-delivery-item.ready-to-ship',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
        ];
    }
}
