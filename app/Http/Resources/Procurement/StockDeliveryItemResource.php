<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\GoodsIn\StockDeliveryItemBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;

        $locations = $item->relationLoaded('orgStockLocations')
            ? $item->getRelation('orgStockLocations')
            : self::locationsQuery()->where('location_org_stocks.org_stock_id', $item->org_stock_id)->get();

        $warehouseSlugByLocation = $locations->pluck('warehouse_slug', 'location_id');
        $warehouse               = $item->organisation?->warehouses->first();

        $sowings = ($item->relationLoaded('sowings')
            ? $item->sowings
            : $item->sowings()->where('type', SowingTypeEnum::SOW)->with('location')->orderBy('id')->get())
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

        $itemBatches = $item->relationLoaded('batches') ? $item->batches : $item->batches()->with('batchCode')->get();
        $placedBatches = $itemBatches->isEmpty() ? [] : $item->placedBatchQuantities();
        $batches = $itemBatches->map(fn (StockDeliveryItemBatch $batch) => [
            'code'        => $batch->batchCode->code,
            'expiry_date' => $batch->batchCode->expiry_date?->toDateString(),
            'quantity'    => (float) $batch->quantity,
            'placed'      => round($placedBatches[$batch->batch_code_id] ?? 0, 4),
        ])->all();

        $checked     = (float) $item->unit_quantity_checked;
        $placed      = (float) $item->unit_quantity_placed;
        $unitsPerSko = $item->unitsPerSko();

        $isEditable = $item->state !== StockDeliveryItemStateEnum::CANCELLED
            && $item->stockDelivery?->isInGoodsIn();

        $isManagedByPartner = (bool) $item->stockDelivery?->isManagedByPartner();

        $canPlace = $isEditable && $checked >= 1 && $placed < $checked;
        $canCheck = in_array($item->state, [
            StockDeliveryItemStateEnum::RECEIVED,
            StockDeliveryItemStateEnum::CHECKED,
            StockDeliveryItemStateEnum::NOT_RECEIVED,
        ], true) || ($isEditable && $item->state === StockDeliveryItemStateEnum::PLACED);

        return [
            'id'                    => $item->id,
            'slug'                  => $supplierProduct?->slug,
            'code'                  => $supplierProduct?->code ?? $item->org_stock_code,
            'name'                  => $supplierProduct?->name ?? $item->org_stock_name,
            'units_per_pack'        => $supplierProduct?->units_per_pack ?? $unitsPerSko,
            'units_per_carton'      => $supplierProduct?->units_per_carton ?? $unitsPerSko,
            'unit_quantity'         => $item->unit_quantity,
            'unit_quantity_checked' => $item->unit_quantity_checked,
            'unit_quantity_placed'  => $item->unit_quantity_placed,
            'units_per_sko'         => $unitsPerSko,
            'sko_quantity'          => round((float) $item->unit_quantity / $unitsPerSko, 4),
            'sko_quantity_checked'  => round($checked / $unitsPerSko, 4),
            'sko_quantity_placed'   => round($placed / $unitsPerSko, 4),
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
            'is_new_org_stock'      => $item->org_stock_id && $item->has_been_in_warehouse === false,
            'confirmRoute'          => !$isManagedByPartner && $item->state === StockDeliveryItemStateEnum::IN_PROCESS ? [
                'name'       => 'grp.models.stock-delivery-item.confirm',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'readyToShipRoute'      => !$isManagedByPartner && $item->state === StockDeliveryItemStateEnum::CONFIRMED ? [
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
            'receivedAfterAllRoute' => $item->canBeReceivedAfterAll() ? [
                'name'       => 'grp.models.stock-delivery-item.set-all-checked',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'placement_remaining'   => round(max(0, $checked - $placed) / $unitsPerSko, 4),
            'has_available_qty'     => $checked - $placed > 0,
            'is_editable'           => $isEditable,
            'locations'             => $locations,
            'has_picking_location'  => $locations->contains(fn ($location) => $location->default_wholesale_picking_location || $location->default_dropshipping_picking_location),
            'warehouse_area'        => $warehouseArea,
            'warehouse_slug'        => $locations->first()?->warehouse_slug,
            'searchLocationsRoute'  => $warehouse && (request()->route('organisation')?->id ?? $item->organisation_id) === $item->organisation_id ? [
                'name'       => 'grp.org.warehouses.show.infrastructure.locations.index.excluded_in_org_stock',
                'parameters' => [
                    'organisation' => $item->organisation->slug,
                    'warehouse'    => $warehouse->slug,
                    'orgStock'     => $item->orgStock?->slug,
                ],
            ] : null,
            'sowings'               => $sowings,
            'batches'               => $batches,
            'is_batch_tracked'      => (bool) ($item->is_batch_tracked ?? $item->orgStock?->stock?->stockFamily?->is_batch_tracked),
            'batchesRoute'          => $isEditable && $checked > 0 ? [
                'name'       => 'grp.models.stock-delivery-item.batches',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
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

    public static function locationsQuery(): Builder
    {
        return DB::table('location_org_stocks')
            ->leftJoin('locations', 'location_org_stocks.location_id', '=', 'locations.id')
            ->leftJoin('warehouses', 'location_org_stocks.warehouse_id', '=', 'warehouses.id')
            ->select([
                'location_org_stocks.id',
                'location_org_stocks.org_stock_id',
                'location_org_stocks.quantity',
                'location_org_stocks.default_wholesale_picking_location',
                'location_org_stocks.default_dropshipping_picking_location',
                'locations.id as location_id',
                'locations.code as location_code',
                'locations.slug as location_slug',
                'warehouses.slug as warehouse_slug',
            ])
            ->orderByDesc('location_org_stocks.default_wholesale_picking_location')
            ->orderByDesc('location_org_stocks.default_dropshipping_picking_location')
            ->orderBy('locations.code');
    }
}
