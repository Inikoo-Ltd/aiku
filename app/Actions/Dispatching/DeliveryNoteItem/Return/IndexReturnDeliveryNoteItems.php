<?php

/*
 * author Louis Perez
 * created on 04-05-2026-16h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNoteItem\Return;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\Return\ReturnDeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\Return\ReturnDeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\ReturnDeliveryNote;
use App\Models\Dispatching\ReturnDeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReturnDeliveryNoteItems extends OrgAction
{
    public function handle(ReturnDeliveryNote $parent, $prefix = null, ReturnDeliveryNoteItemStateEnum|null $stateFilter = null): LengthAwarePaginator
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

        $query = QueryBuilder::for(ReturnDeliveryNoteItem::class);

        $query->where('return_delivery_note_items.return_delivery_note_id', $parent->id);
        $query->leftJoin('org_stocks', 'return_delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftJoin('delivery_note_items', 'return_delivery_note_items.delivery_note_items_id', 'delivery_note_items.id');

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

        return $query->defaultSort('return_delivery_note_items.id')
            ->select([
                'return_delivery_note_items.id',
                'return_delivery_note_items.return_state',
                'delivery_note_items.quantity_dispatched as expected_quantity',
                'return_delivery_note_items.total_item_not_returned',
                'return_delivery_note_items.total_item_damaged',
                'return_delivery_note_items.total_item_lost',
                'return_delivery_note_items.total_item_returned',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in as packed_in',
            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'expected_quantity', 'total_item_returned', 'total_item_damaged', 'total_item_lost',  'total_item_not_returned'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(ReturnDeliveryNote $parent, $prefix = null, bool $isEditable = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $isEditable) {
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
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            // $allowAction = ($parent->packer_user_id && $parent->packer_user_id == request()->user()->id);

            // if (!$allowAction && $tempPicker = session('temp_handling_delivery_note')) {
            //     $allowAction = $parent->id == data_get($tempPicker, 'value') && now()->lt(data_get($tempPicker, 'expires_at'));
            // }
            // if (app()->isLocal()) {
            //     $allowAction = true;
            // }


            // if (!$parent || !$allowAction) {
            //     $table->column(key: 'picking_locations', label: __('Pickings'), canBeHidden: false, sortable: false, searchable: false);
            //     $table->column(key: 'quantity_required_readonly', label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            //     $table->column(key: 'quantity_picked_readonly', label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            //     $table->column(key: 'quantity_packed_readonly', label: __('Packed'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            // } else {
            $table->column(key: 'expected_quantity', label: __('Expected Qty'), canBeHidden: false, sortable: false, searchable: false);
            if ($parent->return_state !== ReturnDeliveryNoteStateEnum::QUEUED) {
                $table->column(key: 'total_item_damaged', label: __('Damaged'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'total_item_lost', label: __('Lost'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'total_item_returned', label: __('Returned'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            }

            if (!in_array($parent->return_state, [ReturnDeliveryNoteStateEnum::QUEUED, ReturnDeliveryNoteStateEnum::CANCELLED])) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: false, searchable: false, className: 'w-[250px]');
            }
        };
    }


}
