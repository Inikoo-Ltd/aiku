<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 18:17:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\StoredItemsAudit\Deltas\UI;

use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaStoredItemDeltasInProcess extends RetinaAction
{
    public function handle(StoredItemAudit $storedItemAudit, $prefix = null): LengthAwarePaginator
    {
        $fulfilmentCustomer = $storedItemAudit->fulfilmentCustomer;
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.customer_reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Pallet::class);

        $query->where('fulfilment_customer_id', $fulfilmentCustomer->id);
        $query->where('pallets.status', PalletStatusEnum::STORING);
        $query->where('pallets.state', PalletStateEnum::STORING);

        $query->leftJoin('locations', 'pallets.location_id', '=', 'locations.id');
        $query->leftJoin('warehouses', 'pallets.warehouse_id', '=', 'warehouses.id');

        $query->defaultSort('pallets.id')

            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
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
                'locations.code as location_code',
                'locations.slug as location_slug',
                'warehouses.slug as warehouse_slug',
            )->selectRaw("$storedItemAudit->id. as stored_item_audit_id");

        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $fulfilmentCustomer) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __('No pallets found'),
                'count' => $fulfilmentCustomer->number_pallets,
                'description' => __("This customer don't have any pallets"),
            ];

            $table->withGlobalSearch();

            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'location_code', label: __('location'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('pallet reference'), canBeHidden: false, sortable: true, searchable: true);
            // $table->column(key: 'customer_reference', label: __("Pallet customer's reference"), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'stored_items', label: __("Customer's SKUs"), canBeHidden: false);
            $table->column(key: 'actions', label: '', canBeHidden: false);

            $table->defaultSort('reference');
        };
    }
}
