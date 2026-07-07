<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 13:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexDeliveryNoteItemsStateHandling extends OrgAction
{
    use WithDeliveryNoteItemUI;

    public function handle(DeliveryNote $parent, $prefix = null, bool $ignoreParentPagination = false, array|DeliveryNoteItemStateEnum|null $stateFilter = null, ?int $deliveryNoteItemId = null): LengthAwarePaginator
    {
        $globalSearch = $this->getGlobalSearchFilter();

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->where('delivery_note_items.delivery_note_id', $parent->id);

        if ($deliveryNoteItemId) {
            $query->where('delivery_note_items.id', $deliveryNoteItemId);
        }

        if ($stateFilter) {
            if (is_array($stateFilter)) {
                $query->whereIn('delivery_note_items.state', $stateFilter);
            } else {
                $query->where('delivery_note_items.state', $stateFilter);
            }
        }

        $this->applyDeliveryNoteItemBaseWiths($query);
        $this->applyDeliveryNoteItemBaseJoins($query);
        $this->applyDeliveryNoteItemPickingJoins($query);

        $query->leftjoin('shops', 'shops.id', '=', 'delivery_note_items.shop_id');

        return $query
            ->defaultSort(['locations.sort_code', 'org_stocks.code'])
            ->select(array_merge(
                $this->getDeliveryNoteItemBaseSelect(),
                $this->getDeliveryNoteItemPickingSelect(),
                [
                    'delivery_note_items.notes',
                    'shops.slug as shop_slug',
                ]
            ))
            ->allowedSorts(array_merge($this->getDeliveryNoteItemBaseSorts(), ['picking_position']))
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

            $this->addDeliveryNoteItemBaseTableColumns($table);

            $allowAction = $this->canHandleDeliveryNote($deliveryNote);

            $this->addDeliveryNoteItemQuantityTableColumns($table, $allowAction, false);
            if ($allowAction) {
                $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
                if ($isEditable) {
                    $table->column(key: 'picking_position', label: __('To do actions'), canBeHidden: false, sortable: true);
                }
            }
        };
    }
}
