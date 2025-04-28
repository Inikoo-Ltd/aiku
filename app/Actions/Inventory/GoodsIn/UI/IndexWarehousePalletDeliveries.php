<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\GoodsIn\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveriesTabsEnum;
use App\Http\Resources\Fulfilment\PalletDeliveriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWarehousePalletDeliveries extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;

    private ?string $restriction = null;


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($warehouse, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseHandling(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'handling';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($warehouse, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseBookedIn(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'booked_in';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletDeliveriesTabsEnum::values());

        return $this->handle($warehouse, PalletDeliveriesTabsEnum::DELIVERIES->value);
    }


    protected function getElementGroups(Warehouse $warehouse): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    PalletDeliveryStateEnum::labels(forElements: true),
                    PalletDeliveryStateEnum::count($warehouse, forElements: true)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('pallet_deliveries.state', $elements);
                }
            ],


        ];
    }

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('pallet_deliveries.reference', $value)
                    ->orWhereStartWith('customer_reference', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletDelivery::class);
        $queryBuilder->leftJoin('pallet_delivery_stats', 'pallet_deliveries.id', '=', 'pallet_delivery_stats.pallet_delivery_id');
        $queryBuilder->where('pallet_deliveries.warehouse_id', $warehouse->id);
        $queryBuilder->whereNotIn('pallet_deliveries.state', [
            PalletDeliveryStateEnum::IN_PROCESS,
            PalletDeliveryStateEnum::SUBMITTED,
            PalletDeliveryStateEnum::BOOKED_IN,
            PalletDeliveryStateEnum::NOT_RECEIVED
        ]);


        if ($this->restriction) {
            if ($this->restriction == 'booked_in') {
                $queryBuilder->where('pallet_deliveries.state', PalletDeliveryStateEnum::BOOKED_IN);
            } else {
                $queryBuilder->whereIn(
                    'pallet_deliveries.state',
                    [
                        PalletDeliveryStateEnum::CONFIRMED,
                        PalletDeliveryStateEnum::RECEIVED,
                        PalletDeliveryStateEnum::BOOKING_IN
                    ]
                );
            }
        }


        $queryBuilder->select(
            'pallet_deliveries.id',
            'pallet_deliveries.reference',
            'pallet_deliveries.customer_reference',
            'pallet_delivery_stats.number_pallets',
            'pallet_deliveries.estimated_delivery_date',
            'pallet_deliveries.state',
            'pallet_deliveries.slug'
        );


        $queryBuilder->leftJoin('fulfilment_customers', 'pallet_deliveries.fulfilment_customer_id', '=', 'fulfilment_customers.id')
            ->leftJoin('customers', 'fulfilment_customers.customer_id', '=', 'customers.id')
            ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');


        return $queryBuilder
            ->defaultSort('reference')
            ->allowedSorts(['reference'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse $warehouse, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($warehouse, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('Incoming pallet deliveries to process'),
                        'count' => $warehouse->stats->number_pallet_deliveries
                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);


            foreach ($this->getElementGroups($warehouse) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }


            $table->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'estimated_delivery_date', label: __('estimated delivery date'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'actions', label: __('action'), canBeHidden: false, sortable: true, searchable: true, className: 'hello');
        };
    }

    public function jsonResponse(LengthAwarePaginator $deliveries): AnonymousResourceCollection
    {
        return PalletDeliveriesResource::collection($deliveries);
    }

    public function htmlResponse(LengthAwarePaginator $deliveries, ActionRequest $request): Response
    {
        /** @var Warehouse $warehouse */
        $warehouse = $request->route()->parameter('warehouse');

        $navigation = PalletDeliveriesTabsEnum::navigation();
        unset($navigation[PalletDeliveriesTabsEnum::UPLOADS->value]);

        $subNavigation = [];

        $icon       = ['fal', 'fa-truck-couch'];
        $title      = __('fulfilment deliveries');
        $afterTitle = null;
        $iconRight  = null;

        $model = __('Goods in');


        return Inertia::render(
            'Org/Fulfilment/PalletDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallet deliveries'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => PalletDeliveriesResource::collection($deliveries),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                PalletDeliveriesTabsEnum::DELIVERIES->value => $this->tab == PalletDeliveriesTabsEnum::DELIVERIES->value ?
                    fn () => PalletDeliveriesResource::collection($deliveries)
                    : Inertia::lazy(fn () => PalletDeliveriesResource::collection($deliveries)),


            ]
        )->table($this->tableStructure(warehouse: $warehouse, prefix: PalletDeliveriesTabsEnum::DELIVERIES->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Deliveries'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.warehouses.show.incoming.pallet_deliveries.index' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ]
                )
            ),
        };
    }
}
