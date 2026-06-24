<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentPickingSessionPalletItems extends OrgAction
{
    public function handle(PickingSession $pickingSession, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallet_returns.reference', $value)
                    ->orWhereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class)
            ->join('pallet_return_items', 'pallet_return_items.pallet_id', '=', 'pallets.id')
            ->join('pallet_returns', 'pallet_returns.id', '=', 'pallet_return_items.pallet_return_id')
            ->leftJoin('fulfilments', 'pallets.fulfilment_id', '=', 'fulfilments.id')
            ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', '=', 'pallets.fulfilment_customer_id')
            ->leftJoin('customers', 'customers.id', '=', 'fulfilment_customers.customer_id')
            ->leftJoin('locations', 'locations.id', '=', 'pallets.location_id')
            ->where('pallet_return_items.picking_session_id', $pickingSession->id)
            ->where('pallet_return_items.type', 'Pallet');

        $query->defaultSort('pallets.id')
            ->select(
                'pallet_return_items.id',
                'pallet_return_items.pallet_return_id as pallet_return_id',
                'pallet_returns.reference as pallet_return_reference',
                'pallet_returns.slug as pallet_return_slug',
                'pallet_returns.type as pallet_return_type',
                'pallets.id as pallet_id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallet_return_items.state as pivot_state',
                'pallets.state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.received_at',
                'pallets.location_id',
                'pallets.fulfilment_customer_id',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id',
                'locations.slug as location_slug',
                'locations.code as location_code',
                'fulfilments.slug as fulfilment_slug',
                'customers.slug as fulfilment_customer_slug',
                DB::raw('pallet_returns.state as pallet_return_state')
            );

        return $query->allowedSorts(['pallet_return_reference', 'customer_reference', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $items): AnonymousResourceCollection
    {
        return PalletReturnItemsUIResource::collection($items);
    }

    public function tableStructure(PickingSession $pickingSession, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table->withGlobalSearch();

            $table->column(key: 'pallet_return_reference', label: __('Return'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'reference', label: __('Pallet ID'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'stored_items', label: __("Customer's SKUs"), canBeHidden: false);
            $table->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true);
            // $table->column(key: 'actions', label: __('Actions'), canBeHidden: false, searchable: false);

            $table->defaultSort('pallet_return_reference');
        };
    }
}
