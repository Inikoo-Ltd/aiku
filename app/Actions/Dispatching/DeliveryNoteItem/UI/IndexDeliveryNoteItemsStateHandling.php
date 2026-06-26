<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 13:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItemsStateHandling extends OrgAction
{
    public function handle(DeliveryNote $parent, $prefix = null, bool $ignoreParentPagination = false, array|DeliveryNoteItemStateEnum|null $stateFilter = null): LengthAwarePaginator
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

        $query->where('delivery_note_items.delivery_note_id', $parent->id);

        if ($stateFilter) {
            if (is_array($stateFilter)) {
                $query->whereIn('delivery_note_items.state', $stateFilter);
            } else {
                $query->where('delivery_note_items.state', $stateFilter);
            }
        }

        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftJoin('batch_codes', 'delivery_note_items.batch_code_id', '=', 'batch_codes.id');
        $query->leftjoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id');
        $query->leftjoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id');
        $query->with('orgStock.tradeUnits');

        $query->leftjoin('shops', 'shops.id', '=', 'delivery_note_items.shop_id');
        return $query
            ->defaultSort('locations.sort_code', 'org_stocks.code')
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
                'delivery_note_items.batch_code_id',
                'delivery_note_items.organisation_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(batch_codes.code, delivery_note_items.batch_code) as batch_code'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(batch_codes.expiry_date, delivery_note_items.expiry_date) as expiry_date'),
                'delivery_note_items.notes',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in',
                'locations.sort_code as picking_position',
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
                'shops.slug as shop_slug',
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state', 'picking_position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($ignoreParentPagination ? 'deliveryNoteItems' : $prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null, ?DeliveryNote $deliveryNote = null, bool $isEditable = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $deliveryNote, $isEditable) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withLabelRecord([__('delivery note'), __('delivery notes')])
                ->withEmptyState(
                    [
                        'title' => __("delivery note empty"),
                    ]
                )->defaultSort('picking_position');

            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            $handler = $deliveryNote->picker_user_id;

            if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
                $handler = $deliveryNote->packer_user_id;
            }

            $allowAction = ($handler && $handler == request()->user()->id);

            if (!$allowAction && $tempHandler = session('temp_handling_delivery_note')) {
                $allowAction = $deliveryNote->id == data_get($tempHandler, 'value') && now()->lt(data_get($tempHandler, 'expires_at'));
            }

            if (!$deliveryNote || !$allowAction) {
                $table->column(key: 'quantity_required_readonly', label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'quantity_picked_readonly', label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            } else {
                $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
                if ($isEditable) {
                    $table->column(key: 'picking_position', label: __('To do actions'), canBeHidden: false, sortable: true);
                }
            }
        };
    }


}
