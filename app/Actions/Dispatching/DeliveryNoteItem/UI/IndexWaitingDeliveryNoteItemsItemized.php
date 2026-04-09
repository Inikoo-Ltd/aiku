<?php

/*
 * Author: Vika Aqordi
 * Created on 09-04-2026-10h-51m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingDeliveryNoteItemsItemized extends OrgAction
{
    public function handle(Warehouse $warehouse, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->leftJoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->leftJoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id')
            ->leftJoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->where('delivery_note_items.state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED);

        return $query->defaultSort('locations.sort_code', 'org_stocks.code')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.quantity_not_picked',
                'delivery_note_items.quantity_packed',
                'delivery_note_items.quantity_dispatched',
                'delivery_note_items.quantity_waiting_warehouse',
                'delivery_note_items.quantity_waiting_crm',
                'delivery_note_items.is_handled',
                'delivery_note_items.batch_code',
                'delivery_note_items.expiry_date',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.reference as delivery_note_reference',
                'delivery_notes.state as delivery_note_state',
                'delivery_notes.customer_notes as delivery_note_customer_notes',
                'delivery_notes.public_notes as delivery_note_public_notes',
                'delivery_notes.internal_notes as delivery_note_internal_notes',
                'delivery_notes.shipping_notes as delivery_note_shipping_notes',
                'delivery_notes.is_premium_dispatch as delivery_note_is_premium_dispatch',
                'delivery_notes.has_extra_packing as delivery_note_has_extra_packing',
                'delivery_notes.shop_type',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in',
                'locations.sort_code as picking_position',
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
            ])
            ->allowedSorts(['org_stock_name', 'org_stock_code', 'picking_position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withEmptyState([
                'title' => __('No waiting items found'),
            ])->defaultSort('picking_position');

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
            $table->column(key: 'picking_position', label: __('Actions'), canBeHidden: false, sortable: true);
        };
    }
}
