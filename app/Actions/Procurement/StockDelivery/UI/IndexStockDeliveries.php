<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:14:56 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStockDeliveries extends OrgAction
{
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;
    use WithOrgSupplierSubNavigation;

    private Warehouse|Organisation|OrgAgent|OrgPartner|OrgSupplier $parent;

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('stock_deliveries.reference', 'ILIKE', "$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StockDelivery::class);

        if ($this->parent instanceof OrgAgent) {
            $query->where('stock_deliveries.organisation_id', $this->parent->agent->organisation->id);
        } elseif ($this->parent instanceof OrgPartner) {
            $query->where('stock_deliveries.organisation_id', $this->parent->partner->id);
        } elseif ($this->parent instanceof OrgSupplier) {
            $query->where('stock_deliveries.parent_type', 'OrgSupplier')->where('stock_deliveries.parent_id', $this->parent->id);
        }

        return $query
            ->defaultSort('stock_deliveries.reference')
            ->select(['slug', 'reference'])
            ->allowedSorts(['reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('reference');
        };
    }

    // public function authorize(ActionRequest $request): bool
    // {
    //     $this->canEdit = $request->user()->authTo('incoming.'.$this->warehouse->id.'.edit');
    //     return $request->user()->authTo('incoming.'.$this->warehouse->id.'.view');

    // }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle();
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgSupplier;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya = true;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return StockDeliveryResource::collection($suppliers);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = __('Stock Deliveries');
        $model = '';
        $icon = [
            'icon' => ['fal', 'fa-truck-container'],
            'title' => __('Stock Deliveries'),
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof OrgAgent) {
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $title = $this->parent->agent->organisation->name;
            $model = '';
            $icon = [
                'icon' => ['fal', 'fa-people-arrows'],
                'title' => __('Stock Deliveries'),
            ];
            $iconRight = [
                'icon' => 'fal fa-truck-container',
            ];
            $afterTitle = [

                'label' => __('Stock Deliveries'),
            ];
        } elseif ($this->parent instanceof OrgPartner) {
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
            $title = $this->parent->partner->name;
            $model = '';
            $icon = [
                'icon' => ['fal', 'fa-users-class'],
                'title' => __('Stock Deliveries'),
            ];
            $iconRight = [
                'icon' => 'fal fa-truck-container',
            ];
            $afterTitle = [

                'label' => __('Stock Deliveries'),
            ];
        } elseif ($this->parent instanceof OrgSupplier) {
            $subNavigation = $this->getOrgSupplierNavigation($this->parent);
            $title = $this->parent->supplier->name;
            $model = '';
            $icon = [
                'icon' => ['fal', 'fa-person-dolly'],
                'title' => __('Stock Deliveries'),
            ];
            $iconRight = [
                'icon' => 'fal fa-truck-container',
            ];
            $afterTitle = [

                'label' => __('Stock Deliveries'),
            ];
        } elseif ($this->parent instanceof Warehouse) {
            $model = __('Goods in');
        }

        return Inertia::render(
            'Procurement/StockDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title' => __('Stock Deliveries'),
                'pageHead' => [
                    'title' => $title,
                    'icon' => $icon,
                    'model' => $model,
                    'afterTitle' => $afterTitle,
                    'iconRight' => $iconRight,
                    'create' => $this->canEdit && $request->route()->getName() == 'grp.org.procurement.stock_deliveries.index' ? [
                        'route' => [
                            'name' => 'grp.org.procurement.stock_deliveries.create',
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                        'label' => __('Stock Deliveries'),
                    ] : false,
                    'subNavigation' => $subNavigation,
                ],
                'data' => StockDeliveryResource::collection($suppliers),

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.stock_deliveries.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.procurement.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Stock deliveries'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                ]
            ),
            'grp.org.warehouses.show.incoming.stock_deliveries.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Stock deliveries'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                ]
            ),
            'grp.org.procurement.org_agents.show.stock-deliveries.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Stock deliveries'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                ]
            ),
            'grp.org.procurement.org_suppliers.show.stock_deliveries.index' => array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Stock deliveries'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                ]
            ),
            'grp.org.procurement.org_partners.show.stock-deliveries.index' => array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($this->parent, $routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.procurement.org_partners.show.stock-deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Stock deliveries'),
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                ]
            )
        };
    }
}
