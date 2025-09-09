<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItemsInPickingSession extends OrgAction
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

        $query->leftJoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->select([
            'delivery_note_items.id as id',
            'delivery_notes.slug as delivery_note_slug',
            'delivery_notes.reference as delivery_note_reference',
            'delivery_note_items.state',
            'delivery_note_items.quantity_required',
            'delivery_note_items.quantity_picked',
            'delivery_note_items.quantity_not_picked',
            'delivery_note_items.quantity_packed',
            'delivery_note_items.quantity_dispatched',
            'delivery_note_items.is_handled',
            'org_stocks.id as org_stock_id',
            'org_stocks.code as org_stock_code',
            'org_stocks.slug as org_stock_slug',
            'org_stocks.name as org_stock_name',
            'org_stocks.packed_in',
        ]);

        return $query
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(PickingSession $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withEmptyState(
                    [
                        'title' => __("No items found"),
                    ]
                );

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'org_stock_code', label: __('SKU'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('SKU Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_required', label: __('Quantity Required'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent->state != PickingSessionStateEnum::IN_PROCESS) {
                $table->column(key: 'quantity_picked', label: __('Quantity Picked'), canBeHidden: false, sortable: true, searchable: true);
                if ($parent->state == PickingSessionStateEnum::HANDLING) {
                    $table->column(key: 'quantity_to_pick', label: __('Todo'), canBeHidden: false, sortable: true, searchable: true);
                }
                $table->column(key: 'action', label: __('action'), canBeHidden: false, sortable: false, searchable: false);
            }
        };
    }


}
