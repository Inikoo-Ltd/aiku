<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Feb 2025 17:18:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturnItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Fulfilment\PalletStoredItemsInPalletReturnResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IndexPalletStoredItemsInReturn extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    private Fulfilment|Warehouse $parent;

    public function handle(PalletReturn $palletReturn, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('stored_items.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturnItem::class)
            ->leftJoin('pallet_stored_items', function ($join) {
                $join->on('pallet_return_items.pallet_stored_item_id', '=', 'pallet_stored_items.id');
            })
            ->leftJoin('pallets', function ($join) {
                $join->on('pallets.id', '=', 'pallet_return_items.pallet_id');
            })
            ->leftJoin('locations', function ($join) {
                $join->on('locations.id', '=', 'pallet_return_items.picking_location_id');
            })
            ->leftJoin('stored_items', function ($join) {
                $join->on('stored_items.id', '=', 'pallet_return_items.stored_item_id');
            })
            ->where('pallet_return_items.pallet_return_id', $palletReturn->id);


        $queryBuilder
            ->defaultSort('pallet_return_items.quantity_ordered')
            ->select(
                [
                    'pallet_return_items.id as id',
                    'pallet_return_items.quantity_ordered',
                    'pallet_return_items.quantity_dispatched',
                    'pallet_return_items.quantity_fail',
                    'pallet_return_items.quantity_cancelled',
                    'pallet_return_items.quantity_picked',
                    'pallet_return_items.state',

                    'locations.code as location_code',
                    'locations.id as location_id',
                    'locations.slug as location_slug',

                    'stored_items.reference as stored_items_reference',
                    'stored_items.id as stored_items_id',
                    'stored_items.slug as stored_items_slug',
                    'stored_items.name as stored_items_name',


                    'pallets.reference as pallets_reference',
                    'pallets.customer_reference as pallets_customer_reference',
                    'pallets.id as pallets_id',
                    'pallets.slug as pallets_slug',

                ]
            );

        return $queryBuilder->allowedSorts(['pallet_return_items.quantity_ordered'])
            ->allowedFilters([$globalSearch])
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

            ];


            if ($palletReturn instanceof Fulfilment) {
                $emptyStateData['description'] = __("There is no stored items this fulfilment shop");
            }
            if ($palletReturn instanceof FulfilmentCustomer) {
                $emptyStateData['description'] = __("This customer don't have any pallets");
            }

            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'total_quantity', label: __('Current stock'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'pallet_stored_items', label: __('Pallets [Location]'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'total_quantity_ordered', label: __('Requested quantity'), canBeHidden: false, sortable: true, searchable: true);
            if ($palletReturn->state === PalletReturnStateEnum::PICKING) {
                $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->defaultSort('pallet_return_items.quantity_ordered');
        };
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($palletReturn);
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return PalletStoredItemsInPalletReturnResource::collection($storedItems);
    }

}
