<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:14:56 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Http\Resources\Procurement\StockDeliveriesResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
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

    protected function getElementGroups(): array
    {
        $elements = [];
        foreach (StockDeliveryStateEnum::cases() as $case) {
            $elements[$case->value] = [
                __(ucfirst(str_replace('_', ' ', $case->value))),
                null,
            ];
        }

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => $elements,
                'engine'   => function ($query, $elements) {
                    $query->whereIn('stock_deliveries.state', $elements);
                },
            ],
        ];
    }

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
        } elseif ($this->parent instanceof Warehouse) {
            $query->where('stock_deliveries.organisation_id', $this->parent->organisation_id);
        } elseif ($this->parent instanceof Organisation) {
            $query->where('stock_deliveries.organisation_id', $this->parent->id);
        }

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $query
            ->defaultSort('-stock_deliveries.date')
            ->select([
                'id',
                'slug',
                'reference',
                'parent_name',
                'state',
                'date',
            ])
            ->allowedSorts(['reference', 'parent_name', 'date'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
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

            $table->betweenDates(['date']);

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Stock Delivery'), __('Stock Deliveries')]);

            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'parent_name', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->maya   = true;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    /** @noinspection PhpUnusedParameterInspection */
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

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $title         = __('Stock Deliveries');
        $icon          = [
            'icon'  => ['fal', 'fa-truck-container'],
            'title' => __('Stock Deliveries'),
        ];
        $model         = '';
        $afterTitle    = null;
        $iconRight     = null;
        $subNavigation = null;

        if ($this->parent instanceof OrgAgent) {
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Stock Deliveries'),
            ];
            $afterTitle    = ['label' => __('Stock Deliveries')];
            $iconRight     = ['icon' => 'fal fa-truck-container'];
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
        } elseif ($this->parent instanceof OrgPartner) {
            $title         = $this->parent->partner->name;
            $icon          = [
                'icon'  => ['fal', 'fa-users-class'],
                'title' => __('Stock Deliveries'),
            ];
            $afterTitle    = ['label' => __('Stock Deliveries')];
            $iconRight     = ['icon' => 'fal fa-truck-container'];
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
        } elseif ($this->parent instanceof OrgSupplier) {
            $title         = $this->parent->supplier->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Stock Deliveries'),
            ];
            $afterTitle    = ['label' => __('Stock Deliveries')];
            $iconRight     = ['icon' => 'fal fa-truck-container'];
            $subNavigation = $this->getOrgSupplierNavigation($this->parent);
        } elseif ($this->parent instanceof Warehouse) {
            $model = __('Goods in');
        }

        return Inertia::render(
            'Procurement/StockDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Stock Deliveries'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'create'        => $this->canEdit && $request->route()->getName() == 'grp.org.procurement.stock_deliveries.index' ? [
                        'route' => [
                            'name'       => 'grp.org.procurement.stock_deliveries.create',
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                        'label' => __('Stock Deliveries'),
                    ] : false,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => StockDeliveryResource::collection($suppliers),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return StockDeliveriesResource::collection($suppliers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.stock_deliveries.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.warehouses.show.incoming.stock_deliveries.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_agents.show.stock-deliveries.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_partners.show.stock-deliveries.index' => array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($this->parent, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.show.stock-deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_suppliers.show.stock_deliveries.index' => array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            )
        };
    }
}
