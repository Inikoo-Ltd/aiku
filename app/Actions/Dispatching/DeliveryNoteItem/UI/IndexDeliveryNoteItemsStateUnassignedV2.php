<?php

/*
 * Author Louis Perez
 * Created on 03-07-2026-15h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexDeliveryNoteItemsStateUnassignedV2 extends OrgAction
{
    use WithDeliveryNoteItemUI;

    public function handle(DeliveryNote $deliveryNote, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->getGlobalSearchFilter();

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->where('delivery_note_items.delivery_note_id', $deliveryNote->id);

        $this->applyDeliveryNoteItemBaseJoins($query);
        $query->with('orgStock.tradeUnits');

        return $query->defaultSort('org_stocks.code')
            ->select($this->getDeliveryNoteItemBaseSelect())
            ->addSelect([
                 'un_numbers' => $this->getUnNumbersSubquery(),
            ])
            ->allowedSorts($this->getDeliveryNoteItemBaseSorts())
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(DeliveryNote $deliveryNote, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($deliveryNote, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withLabelRecord([__('delivery note'),__('delivery notes')])
                ->withEmptyState(
                    [
                        'title' => __("No items found"),
                        'count' => $deliveryNote->number_items
                    ]
                );

            $this->addDeliveryNoteItemBaseTableColumns($table);
            $table->column(key: 'quantity_required', label: __('Quantity required'), canBeHidden: false, sortable: true, searchable: true, type: 'number', align: 'right');
        };
    }


}
