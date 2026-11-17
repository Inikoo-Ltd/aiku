<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Http\Resources\Procurement\OrgSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\ProcurementNote;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\OrgSuppliersTabsEnum;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrgSuppliers extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentOrganisation;

    private Organisation $parent;

    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value)
                    ->orWhereAnyWordStartWith('suppliers.company_name', $value)
                    ->orWhereAnyWordStartWith('suppliers.contact_name', $value)
                    ->orWhereStartWith('agents.code', $value)
                    ->orWhereIn('suppliers.id', SupplierProduct::query()->whereStartWith('supplier_products.code', $value)->select('supplier_products.supplier_id'));
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $agent = $this->getOrganisationAgent($parent);

        if ($agent) {
            $queryBuilder = $this->getAgentSuppliersQuery($agent);
        } else {
            $queryBuilder = QueryBuilder::for(OrgSupplier::class)
                ->join('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')
                ->leftJoin('org_supplier_stats', 'org_supplier_stats.org_supplier_id', 'org_suppliers.id')
                ->leftJoin('org_agents', 'org_agents.id', 'org_suppliers.org_agent_id')
                ->leftJoin('agents', 'agents.id', 'org_agents.agent_id')
                ->where('org_suppliers.organisation_id', $parent->id);

            foreach ($this->getSupplierElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix,
                    default: $elementGroup['default'] ?? null
                );
            }

            $queryBuilder->select([
                'suppliers.id',
                'suppliers.code',
                'suppliers.name',
                'suppliers.company_name',
                'suppliers.contact_name',
                'suppliers.location',
                'org_suppliers.status',
                'org_suppliers.org_agent_id',
                'org_agents.slug as org_agent_slug',
                'agents.code as agent_code',
                'agents.name as agent_name',
                'org_supplier_stats.number_org_supplier_products',
                'org_supplier_stats.number_purchase_orders',
                'org_supplier_stats.number_stock_deliveries',
                'org_suppliers.slug as org_supplier_slug',
            ])
                ->selectRaw(PurchaseOrderStateEnum::countFromStatsSql('org_supplier_stats', PurchaseOrderStateEnum::live()).' as number_open_purchase_orders')
                ->selectSub(ProcurementNote::openIncidentsOfSupplierQuery($parent), 'open_incidents')
                ->tap(fn ($query) => GetSupplierAgreementStatus::make()->selectStatus($query, $parent->group_id));
        }

        return $queryBuilder
            ->selectRaw("coalesce(nullif(suppliers.company_name, ''), suppliers.contact_name, suppliers.name) as official_name")
            ->defaultSort('suppliers.code')
            ->allowedSorts([
                'code',
                AllowedSort::field('name', 'official_name'),
                'agent_code',
                'location',
                'number_org_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSupplierElementGroups(Organisation $organisation): array
    {
        $stats  = $organisation->procurementStats;
        $groups = [
            'type' => [
                'label'    => __('Type'),
                'default'  => 'free,through_agent',
                'elements' => [
                    'free'          => [
                        __('No agent'),
                        $stats->number_active_independent_org_suppliers,
                        null,
                        ['icon' => 'fal fa-person-dolly', 'class' => 'text-green-500'],
                    ],
                    'through_agent' => [
                        __('Through Agent'),
                        $stats->number_active_org_suppliers_in_agents,
                        null,
                        ['icon' => 'fal fa-people-arrows', 'class' => 'text-blue-500'],
                    ],
                    'archived'      => [
                        __('Archived'),
                        $stats->number_archived_org_suppliers,
                        null,
                        ['icon' => 'fal fa-archive', 'class' => 'text-red-500'],
                    ],
                ],
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

        $agreement = GetSupplierAgreementStatus::make()->elementGroup(
            $organisation->group_id,
            fn ($query) => $query->whereIn('id', OrgSupplier::where('organisation_id', $organisation->id)->where('status', true)->select('supplier_id'))
        );
        if ($agreement) {
            $groups['agreement'] = $agreement;
        }

        return $groups;
    }

    /**
     * An agent buys for all our organisations, so each of its suppliers has one org supplier per organisation:
     * the agent sees each supplier once, linked through one of them, with the supplier's own totals.
     */
    private function getAgentSuppliersQuery(Agent $agent): QueryBuilder
    {
        return QueryBuilder::for(OrgSupplier::class)
            ->join('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', 'suppliers.id')
            ->leftJoin('agents', 'agents.id', 'suppliers.agent_id')
            ->whereIn('org_suppliers.id', function ($query) use ($agent) {
                $query->selectRaw('min(org_suppliers.id)')
                    ->from('org_suppliers')
                    ->join('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')
                    ->where('suppliers.agent_id', $agent->id)
                    ->groupBy('org_suppliers.supplier_id');
            })
            ->where('suppliers.status', true)
            ->select([
                'suppliers.code',
                'suppliers.name',
                'suppliers.company_name',
                'suppliers.contact_name',
                'suppliers.location',
                'supplier_stats.number_current_supplier_products as number_org_supplier_products',
                'supplier_stats.number_purchase_orders',
                'supplier_stats.number_stock_deliveries',
                'org_suppliers.slug as org_supplier_slug',
            ])
            ->selectRaw(PurchaseOrderStateEnum::countFromStatsSql('supplier_stats', PurchaseOrderStateEnum::open()).' as number_open_purchase_orders')
            ->tap(fn ($query) => GetSupplierAgreementStatus::make()->selectStatus($query, $agent->group_id));
    }

    public function tableStructure(Organisation $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $agent = $this->getOrganisationAgent($parent);

            if (!$agent) {
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
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No Suppliers Found'),
                    'count' => $agent
                        ? Supplier::where('agent_id', $agent->id)->where('status', true)->count()
                        : $parent->procurementStats->number_org_suppliers,
                ]);

            if (!$agent) {
                $table->column(key: 'status', label: '', canBeHidden: false, type: 'icon');
            }

            $table
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'nickname', label: __('Nickname'), canBeHidden: false);

            if (!$agent) {
                $table->column(key: 'agent_code', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'location', label: __('Supplier location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_org_supplier_products', label: $agent ? __('Products') : __("Supplier's Products"), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right');

            if (GetSupplierAgreementStatus::make()->requiredVersionNumber($parent->group_id) !== null) {
                $table->column(key: 'agreement', label: __('Agreement'), canBeHidden: false);
            }

            $table->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;

        if ($this->getOrganisationAgent($organisation)) {
            $this->initialisation($organisation, $request)->withTab(OrgSuppliersTabsEnum::values());

            return $this->handle($organisation, OrgSuppliersTabsEnum::INDEX->value);
        }

        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        $agent   = $this->getOrganisationAgent($this->parent);
        $isAgent = (bool) $agent;
        $title   = __('Suppliers');

        $response = Inertia::render(
            'Procurement/OrgSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => '(' . $this->parent->code . ') ' . __('Suppliers'),
                'pageHead'    => [
                    'title' => $title,
                    'model' => $isAgent ? null : $this->parent->code,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-person-dolly'],
                        'title' => $title,
                    ],
                    'actions'       => [
                        $this->canEdit && $this->parent instanceof Organisation ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => $isAgent ? __('Add supplier') : __('Add suppliers'),
                            'label'   => $isAgent ? __('Add supplier') : __('Add suppliers'),
                            'route'   => [
                                'name'       => $isAgent ? 'grp.org.procurement.org_suppliers.create_new' : 'grp.org.procurement.org_suppliers.create',
                                'parameters' => [$this->parent->slug],
                            ],
                        ] : false,
                    ],
                ],
                ...($agent ? $this->getAgentTabs($agent, $suppliers, $request) : ['data' => OrgSuppliersResource::collection($suppliers)]),
            ]
        );

        return $agent
            ? $response->table($this->tableStructure($this->parent, prefix: OrgSuppliersTabsEnum::INDEX->value))
            : $response->table($this->tableStructure($this->parent));
    }

    private function getAgentTabs(Agent $agent, LengthAwarePaginator $suppliers, ActionRequest $request): array
    {
        $buyer = Organisation::where('slug', $request->query('buyer'))
            ->whereIn('id', $agent->orgAgents()->select('organisation_id'))
            ->first();

        $dashboard = fn () => GetAgentSuppliersDashboard::run($agent, $this->parent, $buyer, $request->query('currency'), $request->user());
        $index     = fn () => OrgSuppliersResource::collection($suppliers);

        return [
            'tabs'                                => [
                'current'    => $this->tab,
                'navigation' => array_replace_recursive(OrgSuppliersTabsEnum::navigation(), [
                    OrgSuppliersTabsEnum::INDEX->value => [
                        'title'  => __('Supplier list'),
                        'number' => $this->getAgentSuppliersQuery($agent)->count(),
                    ],
                ]),
            ],
            OrgSuppliersTabsEnum::DASHBOARD->value => $this->tab === OrgSuppliersTabsEnum::DASHBOARD->value ? $dashboard : Inertia::optional($dashboard),
            OrgSuppliersTabsEnum::INDEX->value     => $this->tab === OrgSuppliersTabsEnum::INDEX->value ? $index : Inertia::optional($index),
        ];
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return OrgSuppliersResource::collection($suppliers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
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
        );
    }
}
