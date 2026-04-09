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
use Illuminate\Support\Facades\DB;
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
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.reference as delivery_note_reference',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'locations.sort_code as picking_position',
                'warehouse_areas.code as warehouse_area_code',
                DB::raw('(delivery_note_items.quantity_required - COALESCE(delivery_note_items.quantity_picked, 0)) as quantity_waiting'),
            ])
            ->allowedSorts(['org_stock_name', 'org_stock_code', 'quantity_waiting', 'picking_position'])
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

            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_waiting', label: __('Quantity'), canBeHidden: false, sortable: true, align: 'right');
            $table->column(key: 'action', label: __('Action'), canBeHidden: false);
        };
    }
}
