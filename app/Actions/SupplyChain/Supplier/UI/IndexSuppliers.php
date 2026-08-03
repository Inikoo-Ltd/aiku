<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Http\Resources\SupplyChain\SuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSuppliers extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithAgentSubNavigation;
    private array $elementGroups;

    private mixed $parent;

    private string $bucket = 'all';

    protected function getSElementGroups(Group|Agent $parent): array
    {
        if ($parent instanceof Group) {
            [$numberActiveSuppliers, $numberArchivedSuppliers] = match ($this->bucket) {
                'free' => [$parent->supplyChainStats->number_active_independent_suppliers, $parent->supplyChainStats->number_archived_independent_suppliers],
                'in_agents' => [$parent->supplyChainStats->number_active_suppliers_in_agents, $parent->supplyChainStats->number_archived_suppliers_in_agents],
                default => [$parent->supplyChainStats->number_active_suppliers, $parent->supplyChainStats->number_archived_suppliers],
            };
        } else {
            $numberActiveSuppliers   = $parent->stats->number_active_suppliers;
            $numberArchivedSuppliers = $parent->stats->number_archived_suppliers;
        }

        return [
            'status' => [
                'label'    => __('Status'),
                'default'  => 'active',
                'elements' => [
                    'active'   => [__('Active'), $numberActiveSuppliers],
                    'archived' => [__('Archived'), $numberArchivedSuppliers]
                ],

                'engine' => function ($query, $elements) {
                    $query->where('status', array_pop($elements) === 'active');
                }

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

        $queryBuilder = QueryBuilder::for(Supplier::class);


        if (class_basename($parent) == 'Agent') {
            $queryBuilder->where('suppliers.agent_id', $parent->id);
        } else {
            $queryBuilder->where('suppliers.group_id', $parent->id);
            if ($this->bucket == 'free') {
                $queryBuilder->whereNull('suppliers.agent_id');
            } elseif ($this->bucket == 'in_agents') {
                $queryBuilder->whereNotNull('suppliers.agent_id');
            }
        }


        foreach ($this->getSElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null
            );
        }

        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->select(['suppliers.id', 'suppliers.code', 'suppliers.slug', 'suppliers.name', 'suppliers.location as location', 'number_supplier_products', 'number_purchase_orders'])
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', 'suppliers.id')
            ->allowedSorts(['code', 'name', 'agent_name', 'location', 'number_supplier_products', 'number_purchase_orders'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Agent $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            foreach ($this->getSElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null
                );
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Group' => [
                            'title'       => __('No Suppliers'),
                            'description' => $canEdit ? __('Get started by creating a new supplier.') : null,
                            'count'       => $parent->supplyChainStats->number_suppliers,
                            'action'      => $this->canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('New Supplier'),
                                'label'   => __('Supplier'),
                                'route'   => [
                                    'name'       => 'grp.supply-chain.suppliers.create',
                                    'parameters' => []
                                ]
                            ] : null
                        ],
                        'Agent' => [
                            'title'       => __("Agent doesn't have any suppliers"),
                            'description' => $canEdit ? __('Get started by adding a supplier to this agent.') : null,
                            'count'       => $parent->stats->number_suppliers,
                            'action'      => $canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('New Supplier'),
                                'label'   => __('Supplier'),
                                'route'   => [
                                    'name'       => 'grp.supply-chain.agent.show.suppliers.create',
                                    'parameters' => [$parent->slug]
                                ]
                            ] : null
                        ]
                    }
                )
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'number_supplier_products', label: __('Products'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group        = app('group');
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function free(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'free';
        $group        = app('group');
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function inAgents(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_agents';
        $group        = app('group');
        $this->parent = $group;
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

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = match ($this->bucket) {
            'free' => __('Free Suppliers'),
            'in_agents' => __('Agents Suppliers'),
            default => __('Suppliers')
        };
        if ($this->parent instanceof Group) {
            $subNavigation = $this->getSuppliersSubNavigation();
        }
        $model = '';
        $icon  = [
            'icon'  => ['fal', 'fa-person-dolly'],
            'title' => __('Suppliers')
        ];
        $afterTitle = null;
        $iconRight = null;
        $actions = null;
        if ($this->bucket == 'free') {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Supplier'),
                    'route' => [
                        'name'       => 'grp.supply-chain.suppliers.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ],
            ];
        }

        if ($this->parent instanceof Agent) {
            $subNavigation = $this->getAgentNavigation($this->parent);
            $title = $this->parent->organisation->name;
            $icon  = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Suppliers')
            ];
            $iconRight    = [
                'icon' => 'fal fa-person-dolly',
            ];
            $afterTitle = [

                'label'     => __('Suppliers')
            ];
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'primary',
                    'icon'  => 'fal fa-plus',
                    'label' => __('Create Supplier'),
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ],
            ];
        }

        return Inertia::render(
            'SupplyChain/Suppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
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
                            'route' => [
                                'name' => 'grp.supply-chain.suppliers.index'
                            ],
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            ),
            'grp.overview.procurement.suppliers.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.overview.procurement.suppliers.index'
                            ],
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            ),
            default => array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            )
        };
    }

    public function getSuppliersSubNavigation(): array
    {
        return [
            [
                'label'  => __('Free'),
                'root'   => 'grp.supply-chain.suppliers.free',
                'route'  => [
                    'name'       => 'grp.supply-chain.suppliers.free',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_independent_suppliers
            ],
            [
                'label'  => __('Agents'),
                'root'   => 'grp.supply-chain.suppliers.in_agents',
                'route'  => [
                    'name'       => 'grp.supply-chain.suppliers.in_agents',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_suppliers_in_agents
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.supply-chain.suppliers.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.supply-chain.suppliers.index',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_suppliers
            ],
        ];
    }
}
