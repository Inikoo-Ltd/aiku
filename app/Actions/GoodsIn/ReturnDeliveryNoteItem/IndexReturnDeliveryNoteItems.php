<?php

/*
 * author Louis Perez
 * created on 04-05-2026-16h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNoteItem;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReturnDeliveryNoteItems extends OrgAction
{
    public function handle(ReturnDeliveryNote $parent, $prefix = null, ReturnDeliveryNoteItemStateEnum|null $stateFilter = null, bool $crmMode = false): LengthAwarePaginator
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

        $currency = $parent->order->invoices->first()->currency ?? $parent->shop->currency;

        $query = QueryBuilder::for(ReturnDeliveryNoteItem::class);

        $query->where('return_delivery_note_items.return_delivery_note_id', $parent->id);
        $query->leftJoin('delivery_note_items', 'return_delivery_note_items.delivery_note_items_id', 'delivery_note_items.id');
        $query->leftJoin('org_stocks', 'return_delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftjoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id');
        $query->leftjoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id');

        if ($crmMode && in_array($parent->state, [ReturnDeliveryNoteStateEnum::RETURNED,  ReturnDeliveryNoteStateEnum::DONE])) {
            $query->with('transaction');
        }

        $query->with('sowings.location');

        if ($stateFilter) {
            switch ($stateFilter) {
                case ReturnDeliveryNoteItemStateEnum::HANDLING:
                    $query->where(function ($query) {
                        $query->whereNotNull('return_delivery_note_items.handled_at')
                            ->orWhereNull('return_delivery_note_items.processed_at');
                    });
                    break;
                default:
                    $query->where(function ($query) {
                        $query->orWhereNotNull('return_delivery_note_items.processed_at');
                    });
                    break;
            }
        }



        return $query
            ->defaultSort('return_delivery_note_items.id')
            ->select([
                'return_delivery_note_items.id',
                'return_delivery_note_items.original_transaction_id',
                'return_delivery_note_items.state',
                'return_delivery_note_items.total_item_damaged',
                'return_delivery_note_items.total_item_not_returned',
                'return_delivery_note_items.total_item_returned',
                'return_delivery_note_items.total_expected_qty',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in as packed_in',
                'warehouse_areas.code as warehouse_area_code',
                'warehouse_areas.picking_position as warehouse_area_picking_position',
            ])
            ->addSelect([
                DB::raw("'{$parent->warehouse->slug}' as warehouse_slug"),
                DB::raw("'{$parent->organisation->slug}' as organisation_slug"),
                DB::raw("'{$currency->code}' as currency_code"),
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'expected_quantity', 'total_item_returned', 'total_item_damaged', 'total_item_lost',  'total_item_not_returned'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(ReturnDeliveryNote $parent, $prefix = null, bool $isEditable = false, bool $crmMode = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $isEditable, $crmMode) {
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
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent->state != ReturnDeliveryNoteStateEnum::DONE && !$crmMode) {
                $table->column(key: 'expected_quantity', label: __('Expected Qty'), canBeHidden: false, sortable: false, searchable: false);
            }

            if (in_array($parent->state, [ReturnDeliveryNoteStateEnum::RETURNING])) {
                $table->column(key: 'sowings', label: __('Sowings'), canBeHidden: false);
            }

            if (in_array($parent->state, [ReturnDeliveryNoteStateEnum::RETURNING, ReturnDeliveryNoteStateEnum::RETURNED, ReturnDeliveryNoteStateEnum::DONE])) {
                $table->column(key: 'total_item_not_returned', label: __('Not Returned'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'total_item_damaged', label: __('Damaged'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'total_item_returned', label: __('Returned'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            }

            if ($crmMode && in_array($parent->state, [ReturnDeliveryNoteStateEnum::RETURNED])) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: false, searchable: false, align: 'left');
            }

        };
    }


}
