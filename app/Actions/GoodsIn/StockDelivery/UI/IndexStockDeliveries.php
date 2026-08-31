<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:14:56 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Http\Resources\Procurement\StockDeliveriesResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
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
    use WithParentSiblingsNavigation;
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;
    use WithOrgSupplierSubNavigation;
    use WithAgentSubNavigation;
    use WithSupplierSubNavigation;
    use WithAgentOrganisation;

    private Warehouse|Organisation|OrgAgent|OrgPartner|OrgSupplier|Agent|Supplier $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->maya) {
            return true;
        }

        $routeName = $request->route()->getName();

        if (str_starts_with($routeName, 'grp.org.warehouses.')) {
            return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
        }

        if (str_starts_with($routeName, 'grp.org.')) {
            $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

            return $request->user()->authTo("procurement.{$this->organisation->id}.view");
        }

        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    protected function getElementGroups(): array
    {
        $counts = [];
        if (isset($this->parent)) {
            $countsQuery = $this->applyParentFilter(StockDelivery::query());
            $counts      = $countsQuery
                ->selectRaw('stock_deliveries.state, count(*) as total')
                ->groupBy('stock_deliveries.state')
                ->pluck('total', 'state')
                ->all();
        }

        $elements = [];
        foreach (StockDeliveryStateEnum::cases() as $case) {
            $elements[$case->value] = [
                __(ucfirst(str_replace('_', ' ', $case->value))),
                $counts[$case->value] ?? 0,
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

    private function isAgentContext(): bool
    {
        return $this->parent instanceof OrgAgent
            || $this->parent instanceof Agent
            || (bool) $this->getParentOrganisationAgent($this->parent);
    }

    private function applyParentFilter($query)
    {
        $organisationAgent = $this->getParentOrganisationAgent($this->parent);

        if ($this->parent instanceof OrgAgent) {
            $query->where('stock_deliveries.organisation_id', $this->parent->organisation_id)
                ->where('stock_deliveries.agent_id', $this->parent->agent_id);
        } elseif ($this->parent instanceof OrgPartner) {
            $query->where('stock_deliveries.organisation_id', $this->parent->partner->id);
        } elseif ($this->parent instanceof OrgSupplier) {
            $query->where('stock_deliveries.parent_type', 'OrgSupplier')->where('stock_deliveries.parent_id', $this->parent->id);
        } elseif ($this->parent instanceof Warehouse) {
            $query->where('stock_deliveries.organisation_id', $this->parent->organisation_id);
        } elseif ($this->parent instanceof Agent) {
            $query->where('stock_deliveries.agent_id', $this->parent->id);
        } elseif ($this->parent instanceof Supplier) {
            $query->where('stock_deliveries.supplier_id', $this->parent->id);
        } elseif ($organisationAgent) {
            $query->where('stock_deliveries.agent_id', $organisationAgent->id);
        } elseif ($this->parent instanceof Organisation) {
            $query->where('stock_deliveries.organisation_id', $this->parent->id);
        }

        return $query;
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

        $organisationAgent = $this->getParentOrganisationAgent($this->parent);

        $query = QueryBuilder::for(StockDelivery::class);

        $this->applyParentFilter($query);

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        $query->select([
            'stock_deliveries.id',
            'stock_deliveries.slug',
            'stock_deliveries.reference',
            'stock_deliveries.parent_name',
            'stock_deliveries.state',
            'stock_deliveries.date',
        ]);

        if ($organisationAgent || $this->parent instanceof Agent || $this->parent instanceof Supplier) {
            $query
                ->leftJoin('organisations', 'stock_deliveries.organisation_id', 'organisations.id')
                ->addSelect(['organisations.name as organisation_name', 'organisations.slug as organisation_slug']);
        }

        if ($this->isAgentContext()) {
            $query
                ->leftJoin('currencies', 'currencies.id', 'stock_deliveries.currency_id')
                ->leftJoin('organisations as delivery_organisations', 'delivery_organisations.id', 'stock_deliveries.organisation_id')
                ->leftJoin('currencies as org_currencies', 'org_currencies.id', 'delivery_organisations.currency_id')
                ->leftJoin('groups as delivery_groups', 'delivery_groups.id', 'stock_deliveries.group_id')
                ->leftJoin('currencies as grp_currencies', 'grp_currencies.id', 'delivery_groups.currency_id')
                ->addSelect([
                    'stock_deliveries.number_stock_delivery_items_except_cancelled',
                    'stock_deliveries.number_stock_delivery_items',
                    'stock_deliveries.cbm',
                    'stock_deliveries.gross_weight',
                    'stock_deliveries.cost_total',
                    'stock_deliveries.grp_exchange',
                    'stock_deliveries.org_exchange',
                    'currencies.code as currency_code',
                    'org_currencies.code as org_currency_code',
                    'grp_currencies.code as grp_currency_code',
                ]);
        }

        return $query
            ->defaultSort('-stock_deliveries.date')
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
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            if (!$this->isAgentContext()) {
                $table->column(key: 'parent_name', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true);
            }

            if ($this->getParentOrganisationAgent($this->parent)) {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, searchable: true);
            }

            if ($this->isAgentContext()) {
                $table
                    ->column(key: 'items', label: __('Items'), canBeHidden: false, align: 'right')
                    ->column(key: 'cbm', label: __('CBM'), canBeHidden: false, align: 'right')
                    ->column(key: 'gross_weight', label: __('Weight'), canBeHidden: false, align: 'right')
                    ->column(key: 'amount', label: __('Amount'), canBeHidden: false, align: 'right')
                    ->column(key: 'converted_amount', label: __('Converted amount'), canBeHidden: false, align: 'right');
            }

            $table->defaultSort('-date');
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

    public function inAgent(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $agent;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $supplier;
        $this->initialisationFromGroup(group(), $request);

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
        } elseif ($this->parent instanceof Agent) {
            $title         = $this->parent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Stock Deliveries'),
            ];
            $afterTitle    = ['label' => __('Stock Deliveries')];
            $iconRight     = ['icon' => 'fal fa-truck-container'];
            $subNavigation = $this->getAgentNavigation($this->parent);
        } elseif ($this->parent instanceof Supplier) {
            $title         = $this->parent->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Stock Deliveries'),
            ];
            $afterTitle    = ['label' => __('Stock Deliveries')];
            $iconRight     = ['icon' => 'fal fa-truck-container'];
            $subNavigation = $this->getSupplierNavigation($this->parent);
        } elseif ($this->parent instanceof Warehouse) {
            $model = __('Goods in');
        }

        return Inertia::render(
            'Procurement/StockDeliveries',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Stock Deliveries'),
                'navigation'  => $this->getParentSiblingsNavigation($this->parent, $request),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $this->parent instanceof OrgSupplier
                        ? [$this->getOrgSupplierPurchaseOrderAction($this->parent)]
                        : [],
                    'create'        => $this->canEdit && !$this->getParentOrganisationAgent($this->parent) && $request->route()->getName() == 'grp.org.procurement.stock_deliveries.index' ? [
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
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
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
            ),
            'grp.supply-chain.agents.show.stock_deliveries.index' => array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.supply-chain.agents.show.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.supply-chain.suppliers.stock_deliveries.index' => array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Stock deliveries'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.supply-chain.suppliers.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
        };
    }
}
