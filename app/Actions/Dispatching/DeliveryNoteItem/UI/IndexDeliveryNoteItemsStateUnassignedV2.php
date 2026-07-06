<?php

/*
 * Author Louis Perez
 * Created on 03-07-2026-15h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItemsStateUnassignedV2 extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, $prefix = null): LengthAwarePaginator
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

        $query->where('delivery_note_items.delivery_note_id', $deliveryNote->id);

        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->with('orgStock.tradeUnits');

        return $query->defaultSort('org_stocks.code')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.batch_code',
                'delivery_note_items.expiry_date',
                'org_stocks.id as org_stock_id',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.id as org_stock_id',
                'org_stocks.packed_in'
            ])
            ->addSelect([
                 'un_numbers' => DB::table('trade_units')
                     ->join('model_has_trade_units', function ($join) {
                         $join->on('trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
                             ->where('model_has_trade_units.model_type', 'OrgStock');
                     })
                     ->whereColumn('model_has_trade_units.model_id', 'org_stocks.id')
                     ->whereNotNull('trade_units.un_number')
                     ->whereNotNull('trade_units.proper_shipping_name')
                     ->where('trade_units.un_number', '<>', 'None')
                     ->selectRaw('jsonb_object_agg(trade_units.proper_shipping_name, trade_units.un_number)'),
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required'])
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

            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_required', label: __('Quantity required'), canBeHidden: false, sortable: true, searchable: true, type: 'number', align: 'right');
        };
    }


}
