<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Actions\SupplyChain\SupplierMailshot\UI\WithSupplierMailshotsSubNavigation;
use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Http\Resources\SupplyChain\SuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\ProcurementNote;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSuppliers extends OrgAction
{
    use WithSupplierMailshotsSubNavigation;
    use WithParentSiblingsNavigation;
    use WithAgentSubNavigation;
    use WithSupplyChainAuthorisation;

    private Group|Agent $parent;

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
                    __('No agent'),
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

        $groups = [
            'type' => [
                'label'    => __('Type'),
                'elements' => $elements,
                'default'  => implode(',', array_diff(array_keys($elements), ['archived'])),
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

        $agreement = $this->agreementElementGroup($parent);
        if ($agreement) {
            $groups['agreement'] = $agreement;
        }

        return $groups;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function elementGroups(Group|Agent $parent): array
    {
        return $this->getSupplierElementGroups($parent);
    }

    /**
     * Once a Supplier Agreement is published, the list filters on where each supplier stands with it.
     *
     * @return array<string, mixed>|null
     */
    protected function agreementElementGroup(Group|Agent $parent): ?array
    {
        return GetSupplierAgreementStatus::make()->elementGroup($parent->group_id ?? $parent->id, fn ($query) => $query
            ->when($parent instanceof Agent, fn ($query) => $query->where('agent_id', $parent->id), fn ($query) => $query->where('group_id', $parent->id)));
    }

    public function handle(Group|Agent $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value)
                    ->orWhereAnyWordStartWith('suppliers.company_name', $value)
                    ->orWhereAnyWordStartWith('suppliers.contact_name', $value)
                    ->orWhereStartWith('agents.code', $value)
                    ->orWhereHas('supplierProducts', fn ($query) => $query->whereStartWith('supplier_products.code', $value));
            });
        });

        $agentFilter = AllowedFilter::callback('agent', function ($query, $value) {
            $query->where('agents.slug', $value);
        });

        $locationFilter = AllowedFilter::callback('location', function ($query, $value) {
            $query->whereRaw('suppliers.location->>0 = ?', [$value]);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Supplier::class)
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', 'suppliers.id')
            ->leftJoin('agents', 'agents.id', 'suppliers.agent_id');

        if ($parent instanceof Agent) {
            $queryBuilder->where('suppliers.agent_id', $parent->id);
        } else {
            $queryBuilder->where('suppliers.group_id', $parent->id);
        }

        foreach ($this->elementGroups($parent) as $key => $elementGroup) {
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
            ->select([
                'suppliers.id',
                'suppliers.slug',
                'suppliers.code',
                'suppliers.name',
                'suppliers.company_name',
                'suppliers.contact_name',
                'suppliers.location',
                'suppliers.status',
                'suppliers.agent_id',
                'agents.slug as agent_slug',
                'agents.code as agent_code',
                'agents.name as agent_name',
                'supplier_stats.number_supplier_products',
                'supplier_stats.number_purchase_orders',
                'supplier_stats.number_stock_deliveries',
            ])
            ->selectRaw("coalesce(nullif(suppliers.company_name, ''), suppliers.contact_name, suppliers.name) as official_name")
            ->selectRaw(PurchaseOrderStateEnum::countFromStatsSql('supplier_stats', PurchaseOrderStateEnum::live()).' as number_open_purchase_orders')
            ->selectSub(ProcurementNote::openIncidentsOfSupplierQuery(), 'open_incidents')
            ->tap(fn ($query) => GetSupplierAgreementStatus::make()->selectStatus($query, $parent->group_id ?? $parent->id))
            ->allowedSorts([
                'code',
                AllowedSort::field('name', 'official_name'),
                'agent_code',
                'location',
                'number_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch, $agentFilter, $locationFilter])
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

            foreach ($this->elementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null
                );
            }

            if ($parent instanceof Group) {
                $agentOptions = $this->agentFilterOptions($parent);
                $table->selectFilter('agent', $agentOptions->pluck('label', 'slug')->all(), __('Agent'), withFlags: $agentOptions->pluck('country_code', 'slug')->filter()->all());
            }

            $table
                ->selectFilter('location', $this->locationFilterOptions($parent), __('Supplier location'), withFlags: true)
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withEmptyState($this->getEmptyState($parent));

            $table
                ->column(key: 'status', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'nickname', label: __('Nickname'), canBeHidden: false);

            if ($parent instanceof Group) {
                $table->column(key: 'agent_code', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'location', label: __('Supplier location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right');

            if (GetSupplierAgreementStatus::make()->requiredVersionNumber($parent->group_id ?? $parent->id) !== null) {
                $table->column(key: 'agreement', label: __('Agreement'), canBeHidden: false);
            }

            $table->defaultSort('code');
        };
    }

    /**
     * @return Collection<int, array{slug: string, label: string, country_code: string|null}>
     */
    protected function agentFilterOptions(Group $group): Collection
    {
        return Agent::query()
            ->leftJoin('organisations', 'organisations.id', 'agents.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->where('agents.group_id', $group->id)
            ->whereHas('suppliers')
            ->orderBy('agents.code')
            ->get(['agents.slug', 'agents.code', 'agents.name', 'countries.code as country_code'])
            ->map(fn (Agent $agent) => [
                'slug'         => $agent->slug,
                'label'        => $agent->code.($agent->name ? ' ('.$agent->name.')' : ''),
                'country_code' => $agent->country_code,
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function locationFilterOptions(Group|Agent $parent): array
    {
        return Supplier::query()
            ->when(
                $parent instanceof Agent,
                fn ($query) => $query->where('agent_id', $parent->id),
                fn ($query) => $query->where('group_id', $parent->id)
            )
            ->whereRaw("location->>0 <> ''")
            ->selectRaw('location->>0 as country_code, max(location->>1) as country_name')
            ->groupByRaw('location->>0')
            ->orderByRaw('max(location->>1)')
            ->get()
            ->mapWithKeys(fn ($country) => [$country->country_code => $country->country_name ?: $country->country_code])
            ->all();
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
            'title'       => __('No Suppliers'),
            'description' => $this->canEdit ? __('Get started by creating a new supplier.') : null,
            'count'       => $parent->supplyChainStats->number_suppliers,
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

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
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
            $actions       = array_values(array_filter([
                $this->canEdit ? [
                    'type'  => 'button',
                    'style' => 'secondary',
                    'icon'  => 'fal fa-people-arrows',
                    'label' => __('Add Supplier'),
                    'route' => [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.assignable',
                        'parameters' => array_values($request->route()->originalParameters()),
                    ],
                ] : null,
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
            ]));
        } else {
            $subNavigation = $this->getSuppliersSubNavigation();

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
                'navigation'  => $this->getParentSiblingsNavigation($this->parent, $request),
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
                            'label' => __('Suppliers'),
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
