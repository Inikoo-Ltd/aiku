<?php

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletReturnsInPickingSession extends OrgAction
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
            ->where('pivot.picking_session_id', $parent->id);

        $query->select([
            'pallet_returns.id',
            'pallet_returns.slug',
            'pallet_returns.reference',
            'pallet_returns.state',
            'pallet_returns.type',
            'pallet_return_stats.number_pallets as number_pallets',
            'pallet_return_stats.number_stored_items as number_stored_items',
            'pallet_returns.confirmed_at',
            'pallet_returns.picking_at',
            'pallet_returns.picked_at',
            'pallet_returns.dispatched_at',
            'pallet_returns.cancel_at',
        ]);

        return $query
            ->allowedSorts(['reference', 'number_pallets', 'number_stored_items', 'state'])
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
            $table->column(key: 'number_pallets', label: __('Pallets'), sortable: true, searchable: true, align: 'right');
            $table->column(key: 'number_stored_items', label: __('Stored items'), sortable: true, searchable: true, align: 'right');


        };
    }
}
