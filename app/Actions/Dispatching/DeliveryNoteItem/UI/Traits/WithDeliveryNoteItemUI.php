<?php

namespace App\Actions\Dispatching\DeliveryNoteItem\UI\Traits;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

trait WithDeliveryNoteItemUI
{
    protected function getGlobalSearchFilter(): AllowedFilter
    {
        return AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });
    }

    protected function getUnNumbersSubquery(): Builder
    {
        return DB::table('trade_units')
            ->join('model_has_trade_units', function ($join) {
                $join->on('trade_units.id', '=', 'model_has_trade_units.trade_unit_id')
                    ->where('model_has_trade_units.model_type', 'OrgStock');
            })
            ->whereColumn('model_has_trade_units.model_id', 'org_stocks.id')
            ->whereNotNull('trade_units.un_number')
            ->whereNotNull('trade_units.proper_shipping_name')
            ->where('trade_units.un_number', '<>', 'None')
            ->selectRaw('jsonb_object_agg(trade_units.proper_shipping_name, trade_units.un_number)');
    }

    protected function getPickingsSubquery(): Builder
    {
        return DB::table('pickings')
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
                                'location_slug', locations.slug,
                                'location_code', locations.code,
                                'batch_code_id', pickings.batch_code_id,
                                'batch_code', batch_codes.code
                            )
                        )
                    ");
    }

    protected function canHandleDeliveryNote(?DeliveryNote $deliveryNote): bool
    {
        if (!$deliveryNote) {
            return false;
        }

        $handler = $deliveryNote->picker_user_id;

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
            $handler = $deliveryNote->packer_user_id;
        }

        $allowAction = ($handler && $handler == request()->user()->id);

        if (!$allowAction && $tempHandler = session('temp_handling_delivery_note')) {
            $allowAction = $deliveryNote->id == data_get($tempHandler, 'value') && now()->lt(data_get($tempHandler, 'expires_at'));
        }

        return $allowAction;
    }

    protected function addDeliveryNoteItemBaseTableColumns(InertiaTable $table): void
    {
        $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
        $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
    }

    protected function applyDeliveryNoteItemPickingJoins($query): void
    {
        $query->leftjoin('locations', 'locations.id', '=', 'org_stocks.picking_location_id');
        $query->leftjoin('warehouse_areas', 'warehouse_areas.id', '=', 'locations.warehouse_area_id');
    }

    protected function applyDeliveryNoteItemBaseWiths($query): void
    {
        $query->with(['pickings.location.warehouse', 'pickings.batchCode', 'pickings.orgStock.mainBatchCode']);
        $query->with('orgStock.tradeUnits');
    }

    protected function applyDeliveryNoteItemBaseJoins($query): void
    {
        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');
        $query->leftJoin('batch_codes', 'delivery_note_items.batch_code_id', '=', 'batch_codes.id');
    }

    protected function getDeliveryNoteItemBaseSelect(): array
    {
        return [
            'delivery_note_items.id',
            'delivery_note_items.state',
            'delivery_note_items.quantity_required',
            'delivery_note_items.quantity_picked',
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
            'delivery_note_items.quantity_waiting_crm',
            'delivery_note_items.quantity_waiting_warehouse',
        ];
    }

    protected function getDeliveryNoteItemPickingSelect(): array
    {
        return [
            'locations.sort_code as picking_position',
            'warehouse_areas.code as warehouse_area_code',
            'warehouse_areas.picking_position as warehouse_area_picking_position',
        ];
    }

    protected function getDeliveryNoteItemBaseSorts(): array
    {
        return ['id', 'org_stock_name', 'org_stock_code', 'quantity_required', 'quantity_picked', 'quantity_packed', 'state'];
    }

    protected function addDeliveryNoteItemQuantityTableColumns(InertiaTable $table, bool $allowAction, bool $includePacked = true): void
    {
        $suffix = $allowAction ? '' : '_readonly';

        $table->column(key: 'quantity_required'.$suffix, label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        $table->column(key: 'quantity_picked'.$suffix, label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        if ($includePacked) {
            $table->column(key: 'quantity_packed'.$suffix, label: __('Packed'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        }
    }
}
