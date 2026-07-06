<?php

/*
 * Author: Vika Aqordi
 * Created: Thu, 09 Apr 2026 10:51 Malaysia Time, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexWaitingDeliveryNoteItemsItemized extends OrgAction
{
    use WithDeliveryNoteItemUI;

    public function handle(Warehouse $warehouse, string $waitingType, DeliveryNoteStateEnum $state, string $shopType = 'all', ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->getGlobalSearchFilter();

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $oppositeWaitingColumn = $waitingType == 'warehouse' ? 'has_waiting_crm' : 'has_waiting_warehouse';

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id');
        $this->applyDeliveryNoteItemBaseJoins($query);
        $this->applyDeliveryNoteItemPickingJoins($query);

        $query->leftJoin('shops', 'shops.id', '=', 'delivery_notes.shop_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id);

        if ($waitingType == 'warehouse') {
            $query->where('delivery_note_items.has_waiting_warehouse', true);
        } else {
            $query->where('delivery_note_items.has_waiting_crm', true);
        }

        $query->where('delivery_notes.state', $state);


        if ($shopType != 'all') {
            // Get directly from shop.type because some deliveryNote has no shop_type somehow (null), probably old order_data
            $query->where('shops.type', $shopType);
        }

        return $query->defaultSort('locations.sort_code', 'org_stocks.code')
            ->select(array_merge(
                $this->getDeliveryNoteItemBaseSelect(),
                $this->getDeliveryNoteItemPickingSelect(),
                [
                    'delivery_note_items.notes',
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
                    'shops.name as shop_name',
                    'shops.code as shop_code',
                    'shops.slug as shop_slug',
                ]
            ))
            ->selectRaw('(SELECT string_agg(t.name, \', \' ORDER BY t.name) FROM delivery_note_has_trolleys dnt JOIN trolleys t ON t.id = dnt.trolley_id WHERE dnt.delivery_note_id = delivery_notes.id) as trolley_names')
            ->selectRaw('(SELECT string_agg(pb.code, \', \' ORDER BY pb.code) FROM picked_bay_has_delivery_notes pbdn JOIN picked_bays pb ON pb.id = pbdn.picked_bay_id WHERE pbdn.delivery_note_id = delivery_notes.id) as picked_bay_codes')
            ->selectRaw("(SELECT count(*) FROM delivery_note_items dni_opp WHERE dni_opp.delivery_note_id = delivery_notes.id AND dni_opp.$oppositeWaitingColumn = true) as opposite_waiting_count")
            ->allowedSorts(['org_stock_name', 'org_stock_code', 'picking_position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null, bool $readOnly = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $readOnly) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons'  => ['fal', 'fa-hourglass-start'],
                'title'  => __('No waiting items found'),
                'count'  => 0,
            ];

            $table->withEmptyState($emptyStateData)->defaultSort('picking_position');

            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
            if (!$readOnly) {
                $table->column(key: 'picking_position', label: __('Actions'), canBeHidden: false, sortable: true);
            }
        };
    }
}
