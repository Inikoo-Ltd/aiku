<?php

/*
 * author Arya Permana - Kirin
 * created on 25-03-2025-14h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWarehouseManagementAuthorisation;
use App\Http\Resources\Fulfilment\MayaPalletsResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletsInLocation extends OrgAction
{
    use WithWarehouseManagementAuthorisation;

    public function handle(Location $parent, $prefix = null): LengthAwarePaginator
    {
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
        $query->where('pallets.location_id', $parent->id);
        $query->whereNotNull('pallets.slug');
        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
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
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name',
                'fulfilments.slug as fulfilment_slug',
            );
        $query->leftJoin('fulfilments', 'pallets.fulfilment_id', 'fulfilments.id');
        $query->leftJoin('organisations', 'pallets.organisation_id', 'organisations.id');
        $query->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallets.fulfilment_customer_id');
        $query->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id');
        $query->addSelect('customers.name as fulfilment_customer_name', 'customers.slug as fulfilment_customer_slug');

        return $query->allowedSorts([
            'organisation_name',
            'customer_reference',
            'reference',
            'fulfilment_customer_name',
        ])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Location $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __('No pallets found'),
                'count' => $parent->stats->number_pallets
            ];


            $emptyStateData['description'] = __("There is not pallets in this fulfilment shop");


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'fulfilment_customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_reference', label: __("Pallet reference (customer's), notes"), shortLabel: 'PR/N', canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'contents', label: __('Contents'), canBeHidden: false, searchable: true);


            $table->defaultSort('reference');
        };
    }


    public function jsonResponse(LengthAwarePaginator $pallets, ActionRequest $request): AnonymousResourceCollection
    {
        if ($request->hasHeader('Maya-Version')) {
            return MayaPalletsResource::collection($pallets);
        }
        return PalletsResource::collection($pallets);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Location $location, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $location;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($location, 'pallets');
    }
}
