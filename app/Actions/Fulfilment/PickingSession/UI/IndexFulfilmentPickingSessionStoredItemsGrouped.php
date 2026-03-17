<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Fulfilment\PalletReturnItemsWithStoredItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;

class IndexFulfilmentPickingSessionStoredItemsGrouped extends OrgAction
{
    public function handle(PickingSession $pickingSession, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('stored_items.reference', $value);
                $query->orWhereAnyWordStartWith('pallet_returns.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $palletReturnStateSelect = DB::raw('pallet_returns.state as pallet_return_state');

        $query = QueryBuilder::for(StoredItem::class)
            ->join('pallet_return_items', function ($join) use ($pickingSession) {
                $join->on('stored_items.id', '=', 'pallet_return_items.stored_item_id')
                    ->where('pallet_return_items.picking_session_id', '=', $pickingSession->id)
                    ->whereNotNull('pallet_return_items.pallet_return_id');
            })
            ->join('pallet_returns', 'pallet_returns.id', '=', 'pallet_return_items.pallet_return_id');

        $query
            ->defaultSort('pallet_returns.reference', 'stored_items.reference')
            ->select([
                'stored_items.id',
                'stored_items.reference',
                'stored_items.slug',
                'stored_items.name',
                'stored_items.total_quantity',
                'pallet_returns.id as pallet_return_id',
                'pallet_returns.reference as pallet_return_reference',
                'pallet_returns.slug as pallet_return_slug',
                'pallet_returns.type as pallet_return_type',
                $palletReturnStateSelect,
                DB::raw('COALESCE(SUM(pallet_return_items.quantity_ordered), 0) as total_quantity_ordered'),
            ])
            ->groupBy([
                'stored_items.id',
                'stored_items.reference',
                'stored_items.slug',
                'stored_items.name',
                'stored_items.total_quantity',
                'pallet_returns.id',
                'pallet_returns.reference',
                'pallet_returns.slug',
                'pallet_returns.type',
            ]);

        return $query
            ->allowedSorts(['pallet_return_reference', 'reference', 'total_quantity', 'total_quantity_ordered'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $items): AnonymousResourceCollection
    {
        return PalletReturnItemsWithStoredItemsResource::collection($items);
    }

    public function tableStructure(PickingSession $pickingSession, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($pickingSession, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withGlobalSearch();

            $table->column(key: 'pallet_return_reference', label: __('Return'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);

            if ($pickingSession->state === PickingSessionStateEnum::PICKING_FINISHED) {
                $table->column(key: 'actions', label: __('To do actions'), canBeHidden: false);
            }
            $table->defaultSort('pallet_return_reference');
        };
    }
}
