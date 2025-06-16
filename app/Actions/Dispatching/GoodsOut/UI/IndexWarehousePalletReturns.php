<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Feb 2025 23:20:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Fulfilment\WithPalletReturnSubNavigation;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\UI\Fulfilment\PalletReturnsTabsEnum;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletReturn;
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

class IndexWarehousePalletReturns extends OrgAction
{
    use WithPalletReturnSubNavigation;
    use WithFulfilmentWarehouseAuthorisation;


    private ?string $restriction = null;
    private ?string $type = null;


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseHandling(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'handling';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseConfirmed(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'confirmed';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehousePicking(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'picking';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehousePicked(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'picked';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseDispatched(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'dispatched';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouseCancelled(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->restriction = 'cancelled';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnsTabsEnum::values());

        return $this->handle($warehouse, PalletReturnsTabsEnum::RETURNS->value);
    }

    protected function getElementGroups(Warehouse $warehouse): array
    {
        $allowedStates = PalletReturnStateEnum::labels(forElements: true);
        $countStates   = PalletReturnStateEnum::count($warehouse, forElements: true);

        if ($this->restriction === 'new') {
            $allowedStates = array_filter($allowedStates, fn ($key) => in_array($key, ['in_process', 'submitted', 'confirmed']), ARRAY_FILTER_USE_KEY);
            $countStates   = array_filter($countStates, fn ($key) => in_array($key, ['in_process', 'submitted', 'confirmed']), ARRAY_FILTER_USE_KEY);
        } elseif ($this->restriction === 'all') {
            $allowedStates = array_filter($allowedStates, fn ($key) => !in_array($key, ['in_process', 'submitted']), ARRAY_FILTER_USE_KEY);
            $countStates   = array_filter($countStates, fn ($key) => !in_array($key, ['in_process', 'submitted']), ARRAY_FILTER_USE_KEY);
        }

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive($allowedStates, $countStates),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('pallet_returns.state', $elements);
                }
            ],
        ];
    }

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('reference', $value)
                    ->orWhereStartWith('customer_reference', $value)
                    ->orWhereStartWith('pallet_returns.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder->leftJoin('pallet_return_stats', 'pallet_return_stats.pallet_return_id', '=', 'pallet_returns.id');
        $queryBuilder->leftJoin('currencies', 'currencies.id', '=', 'pallet_returns.currency_id');
        $queryBuilder->leftJoin('platforms', 'platforms.id', '=', 'pallet_returns.platform_id');

        $queryBuilder->where('pallet_returns.warehouse_id', $warehouse->id);

        if ($this->type) {
            $queryBuilder->where('type', $this->type);
        }

        if ($this->restriction == 'all' || $this->restriction == 'new') {
            foreach ($this->getElementGroups($warehouse) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        if ($this->restriction) {
            switch ($this->restriction) {
                case 'dispatched':
                    $queryBuilder->where('state', PalletReturnStateEnum::DISPATCHED);
                    break;
                case 'confirmed':
                    $queryBuilder->where('state', PalletReturnStateEnum::CONFIRMED);
                    break;
                case 'picking':
                    $queryBuilder->where('state', PalletReturnStateEnum::PICKING);
                    break;
                case 'picked':
                    $queryBuilder->where('state', PalletReturnStateEnum::PICKED);
                    break;
                case 'cancelled':
                    $queryBuilder->where('state', PalletReturnStateEnum::CANCEL);
                    break;
                case 'new':
                    $queryBuilder->whereIn('state', [PalletReturnStateEnum::CONFIRMED, PalletReturnStateEnum::SUBMITTED, PalletReturnStateEnum::IN_PROCESS]);
                    break;
                case 'handling':
                    $queryBuilder->whereIn(
                        'state',
                        [
                            PalletReturnStateEnum::CONFIRMED,
                            PalletReturnStateEnum::PICKING,
                            PalletReturnStateEnum::PICKED
                        ]
                    );
                    break;
                default:
                    //
            }
        }

        $queryBuilder->defaultSort('-date');

        return $queryBuilder
            ->select([
                'pallet_returns.id',
                'state',
                'pallet_returns.slug',
                'reference',
                'customer_reference',
                'number_pallets',
                'number_services',
                'number_physical_goods',
                'date',
                'dispatched_at',
                'pallet_returns.type',
                'number_stored_items',
                'total_amount',
                'confirmed_at',
                'picked_at',
                'picking_at',
                'cancel_at',
                'platforms.name as platform_name',
                'platforms.type as platform_type',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'date', 'state'])
            ->allowedFilters([$globalSearch, 'type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse $warehouse, ?array $modelOperations = null, $prefix = null, string $restriction = 'all'): Closure
    {
        return function (InertiaTable $table) use ($warehouse, $modelOperations, $prefix, $restriction) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($restriction == 'all' || ($warehouse instanceof Fulfilment && $restriction == 'new')) {
                foreach ($this->getElementGroups($warehouse) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No pallet returns found for this warehouse'),
                        'description' => __('This warehouse has not received any pallet returns yet'),
                        'count'       => $warehouse->stats->number_pallet_returns
                    ]
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'platform_name', label: __('platform'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_reference', label: __('customer reference'), canBeHidden: false, sortable: true, searchable: true);

            // if ($this->restriction == 'picking') {
            //     $table->column(key: 'number_stored_items', label: __('stored items'), canBeHidden: false, sortable: true, searchable: true);
            // } else {
            // }
            $table->column(key: 'number_pallets', label: __('pallets'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
            ->column(key: 'number_stored_items', label: __('stored items'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            if ($this->restriction) {
                switch ($this->restriction) {
                    case 'dispatched':
                        $table->column(key: 'dispatched_at', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                        break;
                    case 'confirmed':
                        $table->column(key: 'confirmed_at', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                        break;
                    case 'picking':
                        $table->column(key: 'picking_at', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                        break;
                    case 'picked':
                        $table->column(key: 'picked_at', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                        break;
                    case 'cancelled':
                        $table->column(key: 'cancel_at', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                        break;
                    default:
                        $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
                }
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $returns): AnonymousResourceCollection
    {
        return PalletReturnsResource::collection($returns);
    }

    public function htmlResponse(LengthAwarePaginator $returns, ActionRequest $request): Response
    {
        /** @var Warehouse $warehouse */
        $warehouse = $request->route()->parameter('warehouse');

        $navigation = PalletReturnsTabsEnum::navigation();
        unset($navigation[PalletReturnsTabsEnum::UPLOADS->value]);


        $title      = __('returns');
        $afterTitle = null;


        $icon          = ['fal', 'fa-arrow-from-left'];
        $model         = __('Goods Out');
        $iconRight     = ['fal', 'fa-sign-out-alt'];
        $subNavigation = $this->getPalletReturnSubNavigation($warehouse, $request);


        $actions = [];




        return Inertia::render(
            'Org/Fulfilment/PalletReturns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pallet returns'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => PalletReturnsResource::collection($returns),

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],

                PalletReturnsTabsEnum::RETURNS->value => $this->tab == PalletReturnsTabsEnum::RETURNS->value ?
                    fn () => PalletReturnsResource::collection($returns)
                    : Inertia::lazy(fn () => PalletReturnsResource::collection($returns))

            ]
        )->table($this->tableStructure(warehouse: $warehouse, prefix: PalletReturnsTabsEnum::RETURNS->value, restriction: $this->restriction));

    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Returns'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.pallet-returns.index',
            'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index',
            'grp.org.warehouses.show.dispatching.pallet-returns.picking.index',
            'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index',
            'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index',
            'grp.org.warehouses.show.dispatching.pallet-returns.picked.index' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.pallet-returns.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ]
                )
            ),

        };
    }
}
