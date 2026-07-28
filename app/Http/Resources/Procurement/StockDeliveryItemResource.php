<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;

        $locations = DB::table('location_org_stocks')
            ->leftJoin('locations', 'location_org_stocks.location_id', '=', 'locations.id')
            ->leftJoin('warehouses', 'location_org_stocks.warehouse_id', '=', 'warehouses.id')
            ->where('location_org_stocks.org_stock_id', $item->org_stock_id)
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.quantity',
                'locations.id as location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
                'warehouses.slug as warehouse_slug',
            ])
            ->orderBy('locations.code')
            ->get();

        $warehouseSlugByLocation = $locations->pluck('warehouse_slug', 'location_id');

        $sowings = $item->sowings()
            ->where('type', SowingTypeEnum::SOW)
            ->with('location')
            ->orderBy('id')
            ->get()
            ->map(fn (Sowing $sowing) => [
                'id'                => $sowing->id,
                'type'              => $sowing->type,
                'quantity'          => (float) $sowing->quantity,
                'location_code'     => $sowing->location?->code,
                'location_slug'     => $sowing->location?->slug,
                'warehouse_slug'    => $warehouseSlugByLocation[$sowing->location_id] ?? null,
                'undo_sowing_route' => [
                    'name'       => 'grp.models.sowing.delete',
                    'parameters' => ['sowing' => $sowing->id],
                    'method'     => 'delete',
                ],
            ])->all();

        $warehouseArea = '';
        if ($item->warehouse_area_picking_position) {
            $warehouseArea = __('Sort:').': '.$item->warehouse_area_picking_position.' ';
        }

        if ($item->warehouse_area_code) {
            $warehouseArea .= __('Area').': '.$item->warehouse_area_code;
        }

        if ($warehouseArea == '') {
            $warehouseArea = __('No Area');
        }

        $checked = (float) $item->unit_quantity_checked;
        $placed  = (float) $item->unit_quantity_placed;

        $isEditable = $item->state !== StockDeliveryItemStateEnum::CANCELLED
            && in_array($item->stockDelivery?->state, [
                StockDeliveryStateEnum::RECEIVED,
                StockDeliveryStateEnum::CHECKED,
                StockDeliveryStateEnum::BOOKING_IN,
            ], true);

        $canPlace = $isEditable && $checked >= 1 && $placed < $checked;
        $canCheck = in_array($item->state, [
            StockDeliveryItemStateEnum::RECEIVED,
            StockDeliveryItemStateEnum::CHECKED,
            StockDeliveryItemStateEnum::NOT_RECEIVED,
        ], true);

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
            'checkedRoute'          => $canCheck ? [
                'name'       => 'grp.models.stock-delivery-item.set-checked',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'checkAllRoute'         => $canCheck ? [
                'name'       => 'grp.models.stock-delivery-item.set-all-checked',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'placement_remaining'   => max(0, $checked - $placed),
            'has_available_qty'     => $checked - $placed > 0,
            'is_editable'           => $isEditable,
            'locations'             => $locations,
            'warehouse_area'        => $warehouseArea,
            'warehouse_slug'        => $locations->first()?->warehouse_slug,
            'sowings'               => $sowings,
            'placedRoute'           => $canPlace ? [
                'name'       => 'grp.models.stock-delivery-item.place',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'placeAllRoute'         => $canPlace ? [
                'name'       => 'grp.models.stock-delivery-item.place-all',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
        ];
    }
}
