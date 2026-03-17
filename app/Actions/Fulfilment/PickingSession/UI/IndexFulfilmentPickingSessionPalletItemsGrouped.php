<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\PickingSession;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\DB;

class IndexFulfilmentPickingSessionPalletItemsGrouped extends OrgAction
{
    public function handle(PickingSession $pickingSession, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallet_returns.reference', $value)
                    ->orWhereAnyWordStartWith('pallet_returns.customer_reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $baseQuery = PalletReturnItem::query()
            ->join('pallet_returns', 'pallet_returns.id', '=', 'pallet_return_items.pallet_return_id')
            ->where('pallet_return_items.picking_session_id', $pickingSession->id)
            ->where('pallet_return_items.type', 'Pallet');

        $query = QueryBuilder::for($baseQuery);

        return $query
            ->select([
                'pallet_returns.id',
                'pallet_returns.slug',
                'pallet_returns.reference',
                'pallet_returns.state',
                'pallet_returns.type',
                'pallet_returns.customer_reference',
                DB::raw((int) $pickingSession->id.' as picking_session_id'),
            ])
            ->groupBy(
                'pallet_returns.id',
                'pallet_returns.slug',
                'pallet_returns.reference',
                'pallet_returns.state',
                'pallet_returns.type',
                'pallet_returns.customer_reference'
            )
            ->allowedSorts(['reference', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
            $table->column(key: 'pallets', label: __('Pallets'), canBeHidden: false);

            if ($pickingSession->state === PickingSessionStateEnum::PICKING_FINISHED) {
                $table->column(key: 'actions', label: __('Actions'), canBeHidden: false);
            }

            $table->defaultSort('pallet_return_reference');
        };
    }
}
