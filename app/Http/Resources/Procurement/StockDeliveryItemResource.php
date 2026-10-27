<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrdersToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\RemovePurchaseOrderFromStockDelivery;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCbmInContainer;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\Sowing;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\GoodsIn\StockDeliveryItemBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        if ($item->relationLoaded('productLines')) {
            return $this->productRow($item->getRelation('productLines'), $request);
        }

        return $this->lineRow($item, $request);
    }

    /**
     * A product the delivery holds on several purchase order lines: one row with their totals and the purchase orders
     * they came from. Acting on the row acts on all its lines (item_ids). CBM in container and packed inside stay per
     * line, so they are not editable on a product with more than one line.
     */
    private function productRow(Collection $lines, $request): array
    {
        $rows = $lines->map(fn (StockDeliveryItem $line) => $this->lineRow($line, $request));
        $row  = $rows->first();

        $sum = fn (string $key) => round($rows->sum(fn (array $line) => (float) $line[$key]), 4);
        $sumOrNull = fn (string $key) => $rows->contains(fn (array $line) => $line[$key] === null) ? null : $sum($key);

        $row['item_ids']               = $rows->pluck('id')->all();
        $row['confirm_item_ids']       = $rows->filter(fn (array $line) => $line['confirmRoute'])->pluck('id')->values()->all();
        $row['ready_to_ship_item_ids'] = $rows->filter(fn (array $line) => $line['readyToShipRoute'])->pluck('id')->values()->all();
        $row['remove_item_ids']        = $rows->filter(fn (array $line) => $line['removeRoute'])->pluck('id')->values()->all();
        $row['purchase_orders'] = $rows->map(fn (array $line) => Arr::only($line, ['purchase_order_reference', 'purchase_order_slug', 'unit_quantity']))->all();

        if ($rows->count() === 1) {
            return $row;
        }

        foreach (['unit_quantity', 'unit_quantity_checked', 'unit_quantity_placed', 'sko_quantity', 'sko_quantity_checked', 'sko_quantity_placed', 'net_amount', 'org_net_amount', 'cbm_in_container'] as $key) {
            $row[$key] = $sum($key);
        }
        $row['weight']                  = $sumOrNull('weight');
        $row['volume']                  = $sumOrNull('volume');
        $row['is_cbm_in_container_set'] = $rows->contains('is_cbm_in_container_set', true);
        $row['cbmInContainerRoute']     = null;
        $row['packedInsideRoute']       = null;
        $row['confirmRoute']            = $rows->pluck('confirmRoute')->filter()->first();
        $row['readyToShipRoute']        = $row['confirmRoute'] ? null : $rows->pluck('readyToShipRoute')->filter()->first();
        $row['removeRoute']             = $rows->pluck('removeRoute')->filter()->first();

        return $row;
    }

    private function lineRow(StockDeliveryItem $item, $request): array
    {

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
        $canChangeShippingState = !$isManagedByPartner && $item->stockDelivery && $request->user()
            && AddPurchaseOrdersToStockDelivery::userCanAdd($item->stockDelivery, $request->user());

        $canRemoveFromContainer = $canChangeShippingState
            && RemovePurchaseOrderFromStockDelivery::acceptsRemovals($item->stockDelivery)
            && !in_array($item->state, [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED], true);

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
            'units_per_pack'        => $item->orgStock?->packed_in ?: ($supplierProduct?->units_per_pack ?? $unitsPerSko),
            'units_per_carton'      => $supplierProduct?->units_per_carton ?? $unitsPerSko,
            'supplier_unit'         => $supplierProduct?->supplier_unit?->value,
            'units_per_supplier_unit' => $supplierProduct?->supplier_unit ? $supplierProduct->unitsPerSupplierUnit() : null,
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
            'cbm_in_container'      => $item->cbm_in_container === null ? ($item->volume === null ? null : (float) $item->volume) : (float) $item->cbm_in_container,
            'is_cbm_in_container_set' => $item->cbm_in_container !== null,
            'cbmInContainerRoute'   => SetStockDeliveryItemCbmInContainer::canBeEdited($item) ? [
                'name'       => 'grp.models.stock-delivery-item.cbm-in-container',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'packed_inside_stock_delivery_item_id' => $item->packed_inside_stock_delivery_item_id,
            'packedInsideRoute'     => SetStockDeliveryItemCbmInContainer::canBeEdited($item) ? [
                'name'       => 'grp.models.stock-delivery-item.packed-inside',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'state'                 => $item->state->value,
            'state_label'           => StockDeliveryItemStateEnum::labels()[$item->state->value],
            'state_icon'            => StockDeliveryItemStateEnum::stateIcon()[$item->state->value],
            'purchase_order_reference' => $item->purchase_order_reference,
            'purchase_order_slug'   => $item->purchase_order_slug,
            'org_stock_id'          => $item->org_stock_id,
            'org_stock_slug'        => $item->org_stock_slug,
            'org_stock_code'        => $item->org_stock_code,
            'org_stock_name'        => $item->org_stock_name,
            'is_new_org_stock'      => $item->org_stock_id && $item->has_been_in_warehouse === false,
            'confirmRoute'          => $canChangeShippingState && $item->state === StockDeliveryItemStateEnum::IN_PROCESS ? [
                'name'       => 'grp.models.stock-delivery-item.confirm',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'readyToShipRoute'      => $canChangeShippingState && $item->state === StockDeliveryItemStateEnum::CONFIRMED ? [
                'name'       => 'grp.models.stock-delivery-item.ready-to-ship',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'patch',
            ] : null,
            'shippingStateRoute'    => $canChangeShippingState ? [
                'name'       => 'grp.models.stock-delivery.items.shipping-state',
                'parameters' => ['stockDelivery' => $item->stock_delivery_id],
                'method'     => 'patch',
            ] : null,
            'removeRoute'           => $canRemoveFromContainer ? [
                'name'       => 'grp.models.stock-delivery.items.remove',
                'parameters' => ['stockDelivery' => $item->stock_delivery_id],
                'method'     => 'delete',
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
