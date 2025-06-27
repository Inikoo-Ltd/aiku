<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexPalletsInReturn extends OrgAction
{
    use WithFulfilmentShopAuthorisation;


    public function handle(PalletReturn $palletReturn, $prefix = null): LengthAwarePaginator
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


        $query->where('pallets.pallet_return_id', $palletReturn->id);


        $query->leftJoin('locations', 'locations.id', 'pallets.location_id');
        $query->leftJoin('pallet_return_items', 'pallet_return_items.pallet_id', 'pallets.id');


        $query->defaultSort('pallets.id')
            ->select(
                'pallet_return_items.id',
                'pallets.id as pallet_id',
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
                'locations.slug as location_slug',
                'locations.slug as location_code'
            );


        return $query->allowedSorts(['customer_reference', 'reference', 'fulfilment_customer_name', 'id'])
            ->allowedFilters([$globalSearch, 'customer_reference', 'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(PalletReturn $palletReturn, $request, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $request, $palletReturn) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => '',
                'count' => $palletReturn->stats->number_pallets
            ];


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);
            $table->column(key: 'pallet_id', label: __('Id'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'type_icon', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            $customersReferenceLabel = __("Pallet reference (customer's), notes");


            $table->column(key: 'customer_reference', label: $customersReferenceLabel, canBeHidden: false, sortable: true, searchable: true);

            if (!$request->user() instanceof WebUser) {
                $table->column(key: 'location', label: __('Location'), canBeHidden: false, searchable: true);
            }


            $table->column(key: 'actions', label: ' ', canBeHidden: false, searchable: true);


            $table->defaultSort('reference');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

        return $request->user()->authTo(
            [
                'org-supervisor.'.$this->organisation->id,
                'warehouses-view.'.$this->organisation->id
            ]
        );
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletReturnItemsUIResource::collection($pallets);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($palletReturn);
    }
}
