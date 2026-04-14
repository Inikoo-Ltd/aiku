<?php

/*
 * Author: Vika Aqordi
 * Created: Thu, 09 Apr 2026 10:51 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingDeliveryNoteItemsGrouped extends OrgAction
{
    public function handle(Warehouse $warehouse, string $waitingType, DeliveryNoteStateEnum $state, string $shopType = 'all', ?string $prefix = null): LengthAwarePaginator
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
        $query->leftjoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');

        $query->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereHas('deliveryNoteItems', function ($q) use ($waitingType) {
                if ($waitingType == 'warehouse') {
                    $q->where('delivery_note_items.has_waiting_warehouse', true);
                } else {
                    $q->where('delivery_note_items.has_waiting_crm', true);
                }
            });

        $query->where('delivery_notes.state', $state);

        if ($shopType != 'all') {
            // Get directly from shop.type because some deliveryNote has no shop_type somehow (null), probably old order_data
            $query->where('shops.type', $shopType);
        }

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
