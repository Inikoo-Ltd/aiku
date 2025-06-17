<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletStoredItem;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexStoredItemPallets extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    private bool $selectStoredPallets = false;

    private Fulfilment|Warehouse $parent;

    private StoredItem $storedItem;

    protected function getElementGroups(): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    PalletStateEnum::labels(),
                    PalletStateEnum::count($this->organisation)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],

        ];
    }

    public function handle(StoredItem $storedItem, $prefix = null): LengthAwarePaginator
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

        $query = QueryBuilder::for(PalletStoredItem::class);
        $query->leftJoin('pallets', 'pallets.id', 'pallet_stored_items.pallet_id');
        $query->leftJoin('locations', 'locations.id', 'pallets.location_id');
        $query->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallets.fulfilment_customer_id');
        $query->where('pallet_stored_items.stored_item_id', $storedItem->id);
        $query->defaultSort('pallets.id')
            ->select(
                'pallets.id',
                'pallets.slug',
                'pallets.reference',
                'pallets.customer_reference',
                'pallets.notes',
                'pallets.state as pallet_state',
                'pallets.status',
                'pallets.rental_id',
                'pallets.type',
                'pallets.number_stored_items as quantity',
                'pallets.received_at',
                'pallets.location_id',
                'pallets.fulfilment_customer_id',
                'pallets.warehouse_id',
                'pallets.pallet_delivery_id',
                'pallets.pallet_return_id',
                'locations.slug as location_slug',
                'locations.slug as location_code',
                'fulfilment_customers.slug as fulfilment_customer_slug',
                'pallet_stored_items.quantity as pivot_quantity'
            );

        return $query->defaultSort('pallets.id')
            ->allowedSorts(['customer_reference', 'pallets.reference'])
            ->allowedFilters([$globalSearch, 'customer_reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(StoredItem $storedItem, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $storedItem) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("No pallets exist"),
                'count' => 0
            ];



            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            //  if ($storedItem) {
            //      $table->column(key: 'fulfilment_customer_slug', label: __('Customer'), canBeHidden: false, searchable: true);
            //  }

            $table->column(key: 'location_code', label: __('Location'), canBeHidden: false, searchable: true);

            $table->column(key: 'notes', label: __('Notes'), canBeHidden: false, searchable: true);

            //   if ($this->parent ?? null) {
            $table->column(key: 'stored_items_quantity', label: __('Stored items'), canBeHidden: false, searchable: true);
            // } else {
            //      $table->column(key: 'stock', label: __('Stock'), canBeHidden: false, searchable: true);
            //  }

            $table->defaultSort('reference');
        };
    }

    public function jsonResponse(LengthAwarePaginator $pallets): AnonymousResourceCollection
    {
        return PalletsResource::collection($pallets);
    }

    public function htmlResponse(LengthAwarePaginator $pallets, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/Pallets',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallets'),
                'pageHead'    => [
                    'title'   => __('pallets'),
                    'icon'    => ['fal', 'fa-pallet'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('New Delivery'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.create',
                                'parameters' => [
                                    'organisation' => $request->route('organisation'),
                                    'warehouse'    => $request->route('warehouse'),
                                    'fulfilment'   => $request->route('fulfilment')
                                ]
                            ]
                        ]
                    ]
                ],

                'data'        => PalletsResource::collection($pallets),
            ]
        )->table($this->tableStructure($this->storedItem, 'pallets'));
    }



    /** @noinspection PhpUnusedParameterInspection */
    public function inStoredItem(Organisation $organisation, Warehouse $warehouse, StoredItem $storedItem, ActionRequest $request): LengthAwarePaginator
    {
        // parent is used in authorisation
        $this->parent = $warehouse;

        $this->storedItem = $storedItem;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($storedItem);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.warehouses.show.inventory.pallets.current.index', 'grp.org.warehouses.show.inventory.pallets.current.show' =>
            array_merge(
                ShowWarehouse::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.index',
                                'parameters' => [
                                    'organisation' => $routeParameters['organisation'],
                                    'warehouse'    => $routeParameters['warehouse'],
                                ]
                            ],
                            'label' => __('pallets'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),

            'grp.org.fulfilments.show.operations.pallets.current.index' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                                'parameters' => [
                                    'organisation' => $routeParameters['organisation'],
                                    'fulfilment'   => $routeParameters['fulfilment'],
                                ]
                            ],
                            'label' => __('pallets'),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            )
        };
    }
}
