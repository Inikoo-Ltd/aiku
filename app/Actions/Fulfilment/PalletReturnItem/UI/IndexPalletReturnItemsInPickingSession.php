<?php

namespace App\Actions\Fulfilment\PalletReturnItem\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletReturnItemsInPickingSession extends OrgAction
{
    public function handle(PickingSession $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('pallet_returns.reference', $value)
                    ->orWhereStartWith('pallet_returns.customer_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PalletReturn::class);

        $query->join('picking_session_has_pallet_returns as pivot', 'pivot.pallet_return_id', '=', 'pallet_returns.id')
            ->leftJoin('pallet_return_stats', 'pallet_return_stats.pallet_return_id', '=', 'pallet_returns.id')
            ->leftJoin('pallet_return_items', 'pallet_return_items.pallet_return_id', '=', 'pallet_returns.id')
            ->where('pivot.picking_session_id', $parent->id);

        $query->select([
            'pallet_returns.id',
            'pallet_returns.slug',
            'pallet_returns.reference',
            'pallet_returns.customer_reference',
            'pallet_returns.state',
            'pallet_returns.type',
            'pallet_return_stats.number_pallets as number_pallets',
            'pallet_return_stats.number_stored_items as number_stored_items',
            DB::raw("COALESCE(pallet_returns.dispatched_at, pallet_returns.picked_at, pallet_returns.picking_at, pallet_returns.confirmed_at, pallet_returns.date, pallet_returns.created_at) as date"),
            DB::raw("SUM(CASE WHEN pallet_return_items.type = 'Pallet' THEN 1 ELSE 0 END) as pallets_total"),
            DB::raw("SUM(CASE WHEN pallet_return_items.type = 'Pallet' AND pallet_return_items.state = '".PalletReturnItemStateEnum::PICKED->value."' THEN 1 ELSE 0 END) as pallets_picked"),
            DB::raw("SUM(CASE WHEN pallet_return_items.type = 'StoredItem' THEN COALESCE(pallet_return_items.quantity_ordered, 0) ELSE 0 END) as stored_items_ordered"),
            DB::raw("SUM(CASE WHEN pallet_return_items.type = 'StoredItem' THEN COALESCE(pallet_return_items.quantity_picked, 0) ELSE 0 END) as stored_items_picked"),
            DB::raw("CASE WHEN pallet_returns.type = 'pallet' THEN SUM(CASE WHEN pallet_return_items.type = 'Pallet' THEN 1 ELSE 0 END) ELSE SUM(CASE WHEN pallet_return_items.type = 'StoredItem' THEN COALESCE(pallet_return_items.quantity_ordered, 0) ELSE 0 END) END as to_pick"),
            DB::raw("CASE WHEN pallet_returns.type = 'pallet' THEN SUM(CASE WHEN pallet_return_items.type = 'Pallet' AND pallet_return_items.state = '".PalletReturnItemStateEnum::PICKED->value."' THEN 1 ELSE 0 END) ELSE SUM(CASE WHEN pallet_return_items.type = 'StoredItem' THEN COALESCE(pallet_return_items.quantity_picked, 0) ELSE 0 END) END as picked"),
        ]);

        $query->groupBy([
            'pallet_returns.id',
            'pallet_returns.slug',
            'pallet_returns.reference',
            'pallet_returns.customer_reference',
            'pallet_returns.state',
            'pallet_returns.type',
            'pallet_return_stats.number_pallets',
            'pallet_return_stats.number_stored_items',
            'pallet_returns.dispatched_at',
            'pallet_returns.picked_at',
            'pallet_returns.picking_at',
            'pallet_returns.confirmed_at',
            'pallet_returns.date',
            'pallet_returns.created_at',
        ]);

        return $query
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'number_stored_items', 'date', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(PickingSession $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState(
                    [
                        'title' => __("No returns found"),
                    ]
                );

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('Return Ref'), sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __('Customer reference'), sortable: true, searchable: true);
            $table->column(key: 'number_pallets', label: __('Pallets'), sortable: true, searchable: true, align: 'right');
            $table->column(key: 'number_stored_items', label: __('Stored items'), sortable: true, searchable: true, align: 'right');
            if ($parent->state === PickingSessionStateEnum::HANDLING) {
                $table->column(key: 'to_pick', label: __('To pick'), sortable: false, searchable: false, align: 'right');
                $table->column(key: 'picked', label: __('Picked'), sortable: false, searchable: false, align: 'right');
            }
            $table->column(key: 'date', label: __('Date'), sortable: true, searchable: true, align: 'right', type: 'date');
        };
    }
}
