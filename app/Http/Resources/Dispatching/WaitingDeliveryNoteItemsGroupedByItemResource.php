<?php

/*
 * Author: Vika Aqordi
 * Created on 22-04-2026-11h-28m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $org_stock_slug
 * @property mixed $packed_in
 * @property mixed $picking_position
 * @property mixed $warehouse_area_code
 * @property mixed $warehouse_area_picking_position
 */
class WaitingDeliveryNoteItemsGroupedByItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $orgStock = OrgStock::find($this->id);

        $warehouseArea = '';
        if ($this->warehouse_area_picking_position) {
            $warehouseArea = __('Sort:').': '.$this->warehouse_area_picking_position.' ';
        }
        if ($this->warehouse_area_code) {
            $warehouseArea .= __('Area').': '.$this->warehouse_area_code;
        }
        if ($warehouseArea === '') {
            $warehouseArea = __('No Area');
        }

        $waitingItems = DeliveryNoteItem::query()
            ->with('deliveryNote')
            ->where('org_stock_id', $this->id)
            ->whereIn('state', [DeliveryNoteItemStateEnum::HANDLING_BLOCKED, DeliveryNoteItemStateEnum::QUEUED])
            ->whereHas('deliveryNote', function ($q) {
                $q->where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED);
            })
            ->where('has_waiting_warehouse', true)
            ->get();

        return [
            'id'                             => $this->id,
            'org_stock_code'                 => $this->org_stock_code,
            'org_stock_name'                 => $this->org_stock_name,
            'org_stock_slug'                 => $this->org_stock_slug,
            'org_stock_image_thumbnail'      => $orgStock?->tradeUnits->first()?->imageSources(64, 64),
            'packed_in'                      => $this->packed_in ?? 1,
            'picking_position'               => $this->picking_position,
            'warehouse_area'                 => $warehouseArea,
            'delivery_notes'                 => WaitingDeliveryNoteItemsGroupedByItemDeliveryNoteResource::collection($waitingItems)->resolve(),
        ];
    }
}
