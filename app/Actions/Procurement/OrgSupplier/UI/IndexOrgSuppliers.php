<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Http\Resources\Procurement\OrgSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
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

class IndexOrgSuppliers extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithAgentOrganisation;

    private Organisation|OrgAgent $parent;

    protected function getSupplierElementGroups(Organisation|OrgAgent $parent): array
    {
        if ($this->getParentOrganisationAgent($parent)) {
            return [];
        }

        if ($parent instanceof OrgAgent) {
            $elements = [
                'through_agent' => [
                    __('Through Agent'),
                    $parent->stats->number_active_org_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-people-arrows',
                        'class' => 'text-blue-500',
                    ],
                ],
                'archived'      => [
                    __('Archived'),
                    $parent->stats->number_archived_org_suppliers,
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
                    $parent->procurementStats->number_active_independent_org_suppliers,
                    null,
                    [
                        'icon'  => 'fal fa-person-dolly',
                        'class' => 'text-green-500',
                    ],
                ],
                'through_agent' => [
                    __('Through Agent'),
                    $parent->procurementStats->number_active_org_suppliers_in_agents,
                    null,
                    [
                        'icon'  => 'fal fa-people-arrows',
                        'class' => 'text-blue-500',
                    ],
                ],
                'archived'      => [
                    __('Archived'),
                    $parent->procurementStats->number_archived_org_suppliers,
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
                                    'free'          => $query->where('org_suppliers.status', true)->whereNull('org_suppliers.org_agent_id'),
                                    'through_agent' => $query->where('org_suppliers.status', true)->whereNotNull('org_suppliers.org_agent_id'),
                                    'archived'      => $query->where('org_suppliers.status', false),
                                };
                            });
                        }
                    });
                },
            ],
        ];
    }

    public function handle(OrgAgent|Organisation $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgSupplier::class)
            ->leftJoin('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')
            ->leftJoin('org_supplier_stats', 'org_supplier_stats.org_supplier_id', 'org_suppliers.id');

        $organisationAgent = $this->getParentOrganisationAgent($parent);

        if ($parent instanceof OrgAgent) {
            $queryBuilder->where('org_suppliers.org_agent_id', $parent->id);
        } elseif ($organisationAgent) {
            $queryBuilder->where(
                fn ($query) => $query->where('org_suppliers.agent_id', $organisationAgent->id)
                    ->orWhere('org_suppliers.organisation_id', $parent->id)
            );
        } else {
            $queryBuilder->where('org_suppliers.organisation_id', $parent->id);
        }

        foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->select([
            'suppliers.code',
            'suppliers.name',
            'suppliers.location',
            'org_supplier_stats.number_org_supplier_products',
            'org_supplier_stats.number_purchase_orders',
            'org_supplier_stats.number_stock_deliveries',
            'org_suppliers.status as status',
            'org_suppliers.org_agent_id',
            'org_suppliers.slug as org_supplier_slug',
        ]);

        if ($organisationAgent) {
            $queryBuilder
                ->leftJoin('organisations', 'org_suppliers.organisation_id', 'organisations.id')
                ->addSelect(['organisations.name as organisation_name']);
        }

        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->allowedSorts([
                'code',
                'name',
                'location',
                'number_org_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|OrgAgent $parent, ?array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($parent instanceof OrgAgent) {
                $organisation = $parent->organisation;
            } else {
                $organisation = $parent;
            }

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No Suppliers Found'),
                    'count' => $organisation->inventoryStats->number_warehouse_areas,
                ])
                ->column(key: 'status', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true);

            if ($this->getParentOrganisationAgent($parent)) {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, searchable: true);
            }

            $table
                ->column(key: 'number_org_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
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

        if ($this->parent instanceof OrgAgent) {
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Suppliers'),
            ];
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $afterTitle    = [
                'label' => __('Suppliers'),
            ];
            $iconRight     = [
                'icon' => 'fal fa-person-dolly',
            ];
        }

        return Inertia::render(
            'Procurement/OrgSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Suppliers'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        $this->canEdit && $this->parent instanceof Organisation ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add suppliers'),
                            'label'   => __('Add suppliers'),
                            'route'   => [
                                'name'       => 'grp.org.procurement.org_suppliers.create',
                                'parameters' => [$this->parent->slug],
                            ],
                        ] : false,
                    ],
                ],
                'data'        => OrgSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return OrgSuppliersResource::collection($suppliers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.org_suppliers.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ]
            ),
            'grp.org.procurement.org_agents.show.suppliers.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show.suppliers.index',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ]
            )
        };
    }
}
