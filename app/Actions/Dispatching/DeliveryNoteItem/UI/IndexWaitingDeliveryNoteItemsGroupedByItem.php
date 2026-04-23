<?php

/*
 * Author: Vika Aqordi
 * Created on 22-04-2026-11h-27m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingDeliveryNoteItemsGroupedByItem extends OrgAction
{
    public function handle(Warehouse $warehouse, string $waitingType, DeliveryNoteStateEnum $state, string $shopType = 'all', ?string $prefix = null): LengthAwarePaginator
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

        $query = QueryBuilder::for(OrgStock::class);

        $query->join('delivery_note_items', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
            ->leftJoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id')
            ->leftJoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->where('delivery_notes.state', $state);

        if ($waitingType === 'warehouse') {
            $query->where('delivery_note_items.has_waiting_warehouse', true);
        } else {
            $query->where('delivery_note_items.has_waiting_crm', true);
        }

        if ($shopType !== 'all') {
            $query->where('shops.type', $shopType);
        }

        return $query->defaultSort('locations.sort_code', 'org_stocks.code')
            ->distinct()
            ->select([
                'org_stocks.id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in',
                'locations.sort_code as picking_position',
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
            ])
            ->allowedSorts(['org_stock_code', 'org_stock_name', 'picking_position'])
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
                'icons'  => ['fal', 'fa-hourglass-start'],
                'title'  => __('No waiting items found'),
                'count'  => 0,
            ])->defaultSort('picking_position');

            $table->column(key: 'org_stock_code', label: __('Item'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'delivery_notes', label: __('Delivery Notes'), canBeHidden: false);
        };
    }
}
