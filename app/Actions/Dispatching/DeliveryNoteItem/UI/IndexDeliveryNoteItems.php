<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItems extends OrgAction
{
    public function handle(DeliveryNote $parent, $prefix = null, DeliveryNoteItemStateEnum|null $stateFilter = null): LengthAwarePaginator
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

        $query->where('delivery_note_items.delivery_note_id', $parent->id);

        $query->with(['pickings.location.warehouse', 'pickings.batchCode', 'pickings.orgStock.mainBatchCode']);

        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftJoin('batch_codes', 'delivery_note_items.batch_code_id', '=', 'batch_codes.id');

        $query->leftJoin('packings', function ($join) use ($parent) {
            $join->on('packings.delivery_note_item_id', 'delivery_note_items.id');
        });

        if ($stateFilter) {
            switch ($stateFilter) {
                case DeliveryNoteItemStateEnum::PACKING:
                    $query->whereNull('packings.id')
                        ->where('delivery_note_items.quantity_picked', '!=', 0);
                    break;
                default:
                    $query->where(function ($query) {
                        $query->whereNotNull('packings.id')
                            ->orWhereColumn('delivery_note_items.quantity_picked', 'delivery_note_items.quantity_packed');
                    });
                    break;
            }
        }

        $query->with('orgStock.tradeUnits');


        return $query->defaultSort('delivery_note_items.id')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.quantity_not_picked',
                'delivery_note_items.quantity_packed',
                'delivery_note_items.quantity_dispatched',
                'delivery_note_items.quantity_not_picked',
                'delivery_note_items.is_handled',
                'delivery_note_items.batch_code_id',
                'delivery_note_items.organisation_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(batch_codes.code, delivery_note_items.batch_code) as batch_code'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(batch_codes.expiry_date, delivery_note_items.expiry_date) as expiry_date'),
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in as packed_in',
                'packings.id as packing_id',
                'delivery_note_items.quantity_waiting_crm',
                'delivery_note_items.quantity_waiting_warehouse',

            ])
            ->allowedSorts(['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(DeliveryNote $parent, $prefix = null, bool $isEditable = false): Closure
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

            $handler = $parent->picker_user_id;

            if ($parent->state == DeliveryNoteStateEnum::PACKING) {
                $handler = $parent->packer_user_id;
            }

            $allowAction = ($handler && $handler == request()->user()->id);

            if (!$allowAction && $tempHandler = session('temp_handling_delivery_note')) {
                $allowAction = $parent->id == data_get($tempHandler, 'value') && now()->lt(data_get($tempHandler, 'expires_at'));
            }

            if (!$parent || !$allowAction) {
                $table->column(key: 'picking_locations', label: __('Pickings'), canBeHidden: false, sortable: false, searchable: false);
                $table->column(key: 'quantity_required_readonly', label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'quantity_picked_readonly', label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'quantity_packed_readonly', label: __('Packed'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            } else {
                $table->column(key: 'picking_locations', label: __('Pickings'), canBeHidden: false, sortable: false, searchable: false);
                $table->column(key: 'quantity_required', label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'quantity_picked', label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                $table->column(key: 'quantity_packed', label: __('Packed'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                if ($isEditable) {
                    $table->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: false, searchable: false, className: 'w-[250px]');
                }
            }
        };
    }


}
