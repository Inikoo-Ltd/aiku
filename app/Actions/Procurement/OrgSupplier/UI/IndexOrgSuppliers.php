<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
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
    use WithOrgAgentSubNavigation;
    private Organisation|OrgAgent $parent;

    protected function getSupplierElementGroups(Organisation|OrgAgent $parent): array
    {
        if ($parent instanceof OrgAgent) {
            $stats = $parent->stats;
        } else {
            $stats = $parent->procurementStats;
        }

        return
            [
                'status' => [
                    'label'    => __('status'),
                    'elements' => [
                        'active'   => [
                            __('active'),
                            $stats->number_active_org_suppliers,
                            null,
                            [
                                'icon' => 'fal fa-check',
                                'class' => 'text-green-500'
                            ]
                        ],
                        'archived' => [
                            __('archived'),
                            $stats->number_archived_org_suppliers,
                            null,
                            [
                                'icon' => 'fal fa-check',
                            'class' => 'text-red-500'
                            ]
                        ]
                    ],
                    'engine' => function ($query, $elements) {

                        if (count($elements) == 1) {
                            $query->where('org_suppliers.status', reset($elements) == 'active');
                        }


                    }

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


        $queryBuilder = QueryBuilder::for(OrgSupplier::class);


        if (class_basename($parent) == 'OrgAgent') {

            $queryBuilder->where('org_suppliers.org_agent_id', $parent->id);


        } else {
            $queryBuilder->where('org_suppliers.organisation_id', $parent->id);
            $queryBuilder->whereNull('org_suppliers.org_agent_id');
        }

        foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }


        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->select([
                'suppliers.code', 'suppliers.slug', 'suppliers.name', 'suppliers.location', 'number_org_supplier_products',
                'number_purchase_orders_delivery_state_in_process',
                'number_purchase_orders', 'org_suppliers.status as status', 'org_suppliers.slug as org_supplier_slug'])
            ->leftJoin('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')

            ->leftJoin('org_supplier_stats', 'org_supplier_stats.org_supplier_id', 'org_suppliers.id')

            ->allowedSorts(['code', 'name', 'agent_name', 'supplier_locations', 'number_org_supplier_products', 'number_purchase_orders', 'number_purchase_orders_delivery_state_in_process'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|OrgAgent $parent, array $modelOperations = null, $prefix = null, $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent, $canEdit) {
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
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('no suppliers'),
                        'count' => $organisation->inventoryStats->number_warehouse_areas,

                    ]
                )
                ->column(key: 'status', label: '', type: 'icon', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false)
                ->column(key: 'number_org_supplier_products', label: __('products'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_purchase_orders', label: __('purchase orders'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_purchase_orders_delivery_state_in_process', label: __('orders delivery'), canBeHidden: false, sortable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;

        $this->initialisation($organisation, $request);
        $this->getSupplierElementGroups($orgAgent);

        return $this->handle($orgAgent);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return OrgSuppliersResource::collection($suppliers);
    }


    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = __('suppliers');
        $model = '';
        $icon  = [
            'icon'  => ['fal', 'fa-person-dolly'],
            'title' => __('suppliers')
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof OrgAgent) {
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $title = $this->parent->agent->organisation->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('suppliers')
            ];
            $iconRight    = [
                'icon' => 'fal fa-person-dolly',
            ];
            $afterTitle = [

                'label'     => __('Suppliers')
            ];
        }
        return Inertia::render(
            'Procurement/OrgSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('suppliers'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => OrgSuppliersResource::collection($suppliers),


            ]
        )->table($this->tableStructure($this->parent));
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
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Suppliers'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_agents.show.suppliers.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show.suppliers.index',
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
}
