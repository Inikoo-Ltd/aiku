<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 13:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItemsInPickingSessionStateActive extends OrgAction
{
    public function handle(PickingSession $parent, $prefix = null): LengthAwarePaginator
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

        $query->where('delivery_note_items.picking_session_id', $parent->id);
        $query->leftJoin('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id');
        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftjoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id');

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
                'delivery_note_items.is_handled',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.packed_in',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.id as delivery_note_id',
                'delivery_notes.reference as delivery_note_reference',
                'delivery_notes.state as delivery_note_state',
                'locations.sort_code as picking_position',
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state', 'picking_position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withEmptyState(
                    [
                        'title' => __("delivery note empty"),
                    ]
                )->defaultSort('picking_position');

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
            $table->column(key: 'picking_position', label: __('To do actions'), canBeHidden: false, sortable: true);
        };
    }


}
