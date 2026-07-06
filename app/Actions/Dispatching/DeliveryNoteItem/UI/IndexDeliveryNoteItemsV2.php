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
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteItemsV2 extends OrgAction
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

        return $query->defaultSort('org_stocks.code')
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
                DB::raw('COALESCE(batch_codes.code, delivery_note_items.batch_code) as batch_code'),
                DB::raw('COALESCE(batch_codes.expiry_date, delivery_note_items.expiry_date) as expiry_date'),
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.packed_in as packed_in',
                'org_stocks.main_batch_code_id as org_stocks_batch_code_id',
                'org_stocks.current_batch_codes as org_stocks_batch_code_count',
                'batch_codes.code as org_stocks_batch_code',
                'packings.id as packing_id',
                'delivery_note_items.quantity_waiting_crm',
                'delivery_note_items.quantity_waiting_warehouse',
                DB::raw("'{$parent->warehouse->slug}' as warehouse_slug"),
                DB::raw("'{$parent->warehouse->code}' as warehouse_code"),

            ])
            ->addSelect([
                'un_numbers' => DB::table('trade_units')
                    ->join('model_has_trade_units', function ($join) {
                        $join->on('trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
                            ->where('model_has_trade_units.model_type', 'OrgStock');
                    })
                    ->whereColumn('model_has_trade_units.model_id', 'org_stocks.id')
                    ->whereNotNull('trade_units.un_number')
                    ->where('trade_units.un_number', '<>', 'None')
                    ->selectRaw('jsonb_object_agg(
                            trade_units.proper_shipping_name, 
                            trade_units.un_number
                        )'),
                'pickings' => DB::table('pickings')
                    ->leftJoin('locations', 'locations.id', '=', 'pickings.location_id')
                    ->leftJoin('batch_codes', 'pickings.batch_code_id', '=', 'batch_codes.id')
                    ->whereColumn('pickings.delivery_note_item_id', 'delivery_note_items.id')
                    ->where('pickings.type', '<>', PickingTypeEnum::NOT_PICK)
                    ->where('pickings.quantity', '<>', 0)
                    ->selectRaw("
                        jsonb_object_agg(
                            pickings.id,
                            jsonb_build_object(
                                'quantity', pickings.quantity,
                                'quantity', pickings.quantity,
                                'location_slug', locations.slug,
                                'location_code', locations.code,
                                'batch_code_id', pickings.batch_code_id,
                                'batch_code', batch_codes.code
                            )
                        )
                    "),
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
