<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Http\Resources\SupplyChain\SuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSuppliers extends OrgAction
{
    use WithAgentSubNavigation;
    use WithSupplyChainAuthorisation;

    private Group|Agent $parent;

    private bool $onlyFreeSuppliers = false;

    protected function getSupplierElementGroups(Group|Agent $parent): array
    {
        if ($parent instanceof Agent) {
            $elements = [
                'through_agent' => [
                    __('Through Agent'),
                    $parent->stats->number_active_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-people-arrows',
                        'class' => 'text-blue-500',
                    ],
                ],
                'archived'      => [
                    __('Archived'),
                    $parent->stats->number_archived_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-archive',
                        'class' => 'text-red-500',
                    ],
                ],
            ];
        } else {
            $elements = [
                'free'          => [
                    __('Free'),
                    $parent->supplyChainStats->number_active_independent_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-person-dolly',
                        'class' => 'text-green-500',
                    ],
                ],
                'through_agent' => [
                    __('Through Agent'),
                    $parent->supplyChainStats->number_active_suppliers_in_agents,
                    null,
                    [
                        'icon'  => 'fal fa-people-arrows',
                        'class' => 'text-blue-500',
                    ],
                ],
                'archived'      => [
                    __('Archived'),
                    $parent->supplyChainStats->number_archived_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-archive',
                        'class' => 'text-red-500',
                    ],
                ],
            ];
        }

        return [
            'type' => [
                'label'    => __('Type'),
                'elements' => $elements,
                'engine'   => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        foreach ($elements as $element) {
                            $query->orWhere(function ($query) use ($element) {
                                match ($element) {
                                    'free'          => $query->where('suppliers.status', true)->whereNull('suppliers.agent_id'),
                                    'through_agent' => $query->where('suppliers.status', true)->whereNotNull('suppliers.agent_id'),
                                    'archived'      => $query->where('suppliers.status', false),
                                };
                            });
                        }
                    });
                },
            ],
        ];
    }

    public function handle(Group|Agent $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Supplier::class)
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', 'suppliers.id');

        if ($parent instanceof Agent) {
            $queryBuilder->where('suppliers.agent_id', $parent->id);
        } else {
            $queryBuilder->where('suppliers.group_id', $parent->id);

            if ($this->onlyFreeSuppliers) {
                $queryBuilder
                    ->whereNull('suppliers.agent_id')
                    ->where('suppliers.status', true);
            }
        }

        if (!$this->onlyFreeSuppliers) {
            foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->select([
                'suppliers.id',
                'suppliers.slug',
                'suppliers.code',
                'suppliers.name',
                'suppliers.location',
                'suppliers.status',
                'suppliers.agent_id',
                'supplier_stats.number_supplier_products',
                'supplier_stats.number_purchase_orders',
                'supplier_stats.number_stock_deliveries',
            ])
            ->allowedSorts([
                'code',
                'name',
                'location',
                'number_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Agent $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if (!$this->onlyFreeSuppliers) {
                foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
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
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withEmptyState($this->getEmptyState($parent));

            if (!$this->onlyFreeSuppliers) {
                $table->column(key: 'status', label: '', canBeHidden: false, searchable: true, type: 'icon');
            }

            $table
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    protected function getEmptyState(Group|Agent $parent): array
    {
        if ($parent instanceof Agent) {
            return [
                'title'       => __("Agent doesn't have any suppliers"),
                'description' => $this->canEdit ? __('Get started by adding a supplier to this agent.') : null,
                'count'       => $parent->stats->number_suppliers,
                'action'      => $this->canEdit ? [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New Supplier'),
                    'label'   => __('Supplier'),
                    'route'   => [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.create',
                        'parameters' => [$parent->slug],
                    ],
                ] : null,
            ];
        }

        return [
            'title'       => $this->onlyFreeSuppliers ? __('No Free Suppliers') : __('No Suppliers'),
            'description' => $this->canEdit ? __('Get started by creating a new supplier.') : null,
            'count'       => $this->onlyFreeSuppliers
                ? $parent->supplyChainStats->number_active_independent_suppliers
                : $parent->supplyChainStats->number_suppliers,
            'action'      => $this->canEdit ? [
                'type'    => 'button',
                'style'   => 'create',
                'tooltip' => __('New Supplier'),
                'label'   => __('Supplier'),
                'route'   => [
                    'name'       => 'grp.supply-chain.suppliers.create',
                    'parameters' => [],
                ],
            ] : null,
        ];
    }

    public function asController(ActionRequest $request): LengthAwarePaginator|RedirectResponse
    {
        if ($request->routeIs('grp.supply-chain.suppliers.index') && $request->input('elements.type') === 'through_agent') {
            return redirect()->route('grp.supply-chain.agent_suppliers.index', ['sort' => 'code']);
        }

        $group        = app('group');
        $this->parent = $group;
        $this->onlyFreeSuppliers = $request->routeIs('grp.supply-chain.suppliers.index');
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function inAgent(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return SuppliersResource::collection($suppliers);
    }

    public function htmlResponse(LengthAwarePaginator|RedirectResponse $suppliers, ActionRequest $request): Response|RedirectResponse
    {
        if ($suppliers instanceof RedirectResponse) {
            return $suppliers;
        }

        $title         = __('Suppliers');
        $icon          = [
            'icon'  => ['fal', 'fa-person-dolly'],
            'title' => __('Suppliers'),
        ];
        $subNavigation = null;
        $model         = '';
        $afterTitle    = null;
        $iconRight     = null;
        $actions       = null;

        if ($this->parent instanceof Agent) {
            $title         = $this->parent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Suppliers'),
            ];
            $subNavigation = $this->getAgentNavigation($this->parent);
            $afterTitle    = [
                'label' => __('Suppliers'),
            ];
            $iconRight     = [
                'icon' => 'fal fa-person-dolly',
            ];
            $actions       = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Create Supplier'),
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.create',
                        'parameters' => array_values($request->route()->originalParameters()),
                    ],
                ],
            ];
        } else {
            if ($this->onlyFreeSuppliers) {
                $title = __('Free Suppliers');
                $icon['title'] = __('Free Suppliers');
            }

            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Supplier'),
                    'route' => [
                        'name'       => 'grp.supply-chain.suppliers.create',
                        'parameters' => array_values($request->route()->originalParameters()),
                    ],
                ],
            ];
        }

        return Inertia::render(
            'SupplyChain/Suppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],
                'data'        => SuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.supply-chain.agents.show.suppliers.index' => array_merge(
                ShowAgent::make()->getBreadcrumbs($this->parent, $routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name' => 'grp.supply-chain.suppliers.index',
                            ],
                        ],
                    ],
                ]
            ),
            'grp.overview.procurement.suppliers.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name' => 'grp.overview.procurement.suppliers.index',
                            ],
                        ],
                    ],
                ]
            ),
            default => array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => $this->onlyFreeSuppliers ? __('Free Suppliers') : __('Suppliers'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ]
            )
        };
    }
}
