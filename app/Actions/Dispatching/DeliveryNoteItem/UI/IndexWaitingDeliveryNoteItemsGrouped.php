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
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingDeliveryNoteItemsGrouped extends OrgAction
{
    public function handle(Warehouse $warehouse, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('delivery_notes.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNote::class);

        $query->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereHas('deliveryNoteItems', function ($q) {
                $q->where('state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED);
            });

        return $query->defaultSort('delivery_notes.id')
            ->select([
                'delivery_notes.id as delivery_note_id',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.reference as delivery_note_reference',
                'delivery_notes.state as delivery_note_state',
                'delivery_notes.customer_notes as delivery_note_customer_notes',
                'delivery_notes.public_notes as delivery_note_public_notes',
                'delivery_notes.internal_notes as delivery_note_internal_notes',
                'delivery_notes.shipping_notes as delivery_note_shipping_notes',
                'delivery_notes.is_premium_dispatch as delivery_note_is_premium_dispatch',
                'delivery_notes.has_extra_packing as delivery_note_has_extra_packing',
            ])
            ->allowedSorts(['delivery_note_reference'])
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
            ]);

            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'items', label: __('Items'), canBeHidden: false);
        };
    }
}
