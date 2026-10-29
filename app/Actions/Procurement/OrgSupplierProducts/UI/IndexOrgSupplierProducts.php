<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 11:48:50 British Summer Time,
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Traits\Authorisations\WithAgentProductsAuthorisation;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Models\Helpers\Currency;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\UI\Procurement\OrgSupplierProductsTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\AgentSuppliersDataQualityResource;
use App\Http\Resources\Procurement\OrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
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

class IndexOrgSupplierProducts extends OrgAction
{
    use WithParentSiblingsNavigation;
    use WithAgentProductsAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithOrgSupplierSubNavigation;
    use WithAgentOrganisation;
    use WithAgentProductFacts;

    private OrgSupplier|OrgAgent|Organisation $parent;

    protected function getElementGroups(Organisation|OrgAgent|OrgSupplier $parent): array
    {
        $query = OrgSupplierProduct::query()
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id');

        $organisationAgent = $this->getParentOrganisationAgent($parent);

        if ($parent instanceof OrgAgent) {
            $query->where('org_supplier_products.org_agent_id', $parent->id);
        } elseif ($parent instanceof OrgSupplier) {
            $query->where('org_supplier_products.org_supplier_id', $parent->id);
        } elseif ($organisationAgent) {
            $query->whereIn('org_supplier_products.org_agent_id', function ($query) use ($organisationAgent) {
                $query->select('id')
                    ->from('org_agents')
                    ->where('org_agents.agent_id', $organisationAgent->id);
            });
        } else {
            $query->where('org_supplier_products.organisation_id', $parent->id);
        }

        $counts = $query
            ->selectRaw('supplier_products.state, count(distinct supplier_products.id) as total')
            ->groupBy('supplier_products.state')
            ->pluck('total', 'supplier_products.state');

        $stateCounts = collect(OrgSupplierProductStateEnum::cases())
            ->mapWithKeys(fn (OrgSupplierProductStateEnum $state) => [$state->value => $counts->get($state->value, 0)])
            ->all();

        return [
            'state' => [
                'label'    => __('State'),
                'default'  => OrgSupplierProductStateEnum::ACTIVE->value,
                'elements' => array_merge_recursive(
                    OrgSupplierProductStateEnum::labels(),
                    $stateCounts,
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('supplier_products.state', $elements);
                },
            ],
        ];
    }

    public function handle(Organisation|OrgAgent|OrgSupplier $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($parent) {
            $query->where(function ($query) use ($value, $parent) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value)
                    ->when($this->getParentOrganisationAgent($parent), fn ($query) => $query
                        ->orWhere('f.internal_code', 'ilike', $value.'%')
                        ->orWhere('f.family_code', 'ilike', $value.'%')
                        ->orWhereAnyWordStartWith('suppliers.name', $value));
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgSupplierProduct::class);
        $queryBuilder->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id');
        $queryBuilder->leftJoin('currencies', 'supplier_products.currency_id', 'currencies.id');

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null,
            );
        }

        $organisationAgent = $this->getParentOrganisationAgent($parent);

        if ($parent instanceof OrgAgent) {
            $queryBuilder->where('org_supplier_products.org_agent_id', $parent->id);
        } elseif ($parent instanceof OrgSupplier) {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } elseif ($organisationAgent) {
            $queryBuilder->whereIn('org_supplier_products.org_agent_id', function ($query) use ($organisationAgent) {
                $query->select('id')
                    ->from('org_agents')
                    ->where('org_agents.agent_id', $organisationAgent->id);
            });
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $parent->id);
        }

        $queryBuilder->select([
            'org_supplier_products.id',
            'org_supplier_products.slug',
            'supplier_products.code',
            'supplier_products.name',
            'supplier_products.cost',
            'supplier_products.units_per_carton',
            'currencies.code as currency_code',
        ]);

        if (!$parent instanceof OrgSupplier) {
            $queryBuilder
                ->leftJoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id')
                ->leftJoin('org_suppliers', 'org_supplier_products.org_supplier_id', 'org_suppliers.id')
                ->addSelect(['suppliers.name as supplier_name', 'org_suppliers.slug as org_supplier_slug']);
        }

        if (!$organisationAgent) {
            $queryBuilder->selectSub(
                fn ($query) => $query->from('org_stock_has_org_supplier_products')
                    ->join('org_stocks', 'org_stocks.id', 'org_stock_has_org_supplier_products.org_stock_id')
                    ->whereColumn('org_stock_has_org_supplier_products.org_supplier_product_id', 'org_supplier_products.id')
                    ->orderByDesc('org_stock_has_org_supplier_products.local_priority')
                    ->orderBy('org_stock_has_org_supplier_products.id')
                    ->limit(1)
                    ->select('org_stocks.code'),
                'internal_code'
            );
        }

        if ($organisationAgent) {
            $queryBuilder
                ->select([
                    'supplier_products.code',
                    'supplier_products.name',
                    'supplier_products.cost',
                    'supplier_products.units_per_carton',
                    'currencies.code as currency_code',
                    'suppliers.name as supplier_name',
                ])
                ->selectRaw('min(org_supplier_products.id) as id')
                ->selectRaw('min(org_supplier_products.slug) as slug')
                ->selectRaw('min(org_suppliers.slug) as org_supplier_slug')
                ->groupBy('supplier_products.id', 'currencies.code', 'suppliers.name');

            $this->addAgentProductFacts($queryBuilder, $organisationAgent);
        }

        $groupCurrency = $parent->group->currency;
        $rates         = [];

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->allowedSorts(array_values(array_diff(
                $organisationAgent ? ['code', 'name', 'supplier_name', 'cost', 'internal_code', 'cbm', 'completeness'] : ['code', 'internal_code', 'name', 'supplier_name', 'cost'],
                $this->hideProcurementDetails ? ['cost'] : []
            )))
            ->allowedFilters($organisationAgent ? [$globalSearch, ...$this->agentProductFilters()] : [$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString()
            ->through(function ($row) use ($groupCurrency, &$rates) {
                if ($this->hideProcurementDetails) {
                    $row->cost              = null;
                    $row->group_cost        = null;
                    $row->org_supplier_slug = null;

                    return $row;
                }

                $rates[$row->currency_code] ??= GetCurrencyExchange::run(Currency::where('code', $row->currency_code)->first(), $groupCurrency);
                $row->group_cost          = $rates[$row->currency_code] !== null ? $row->cost * $rates[$row->currency_code] : null;
                $row->group_currency_code = $groupCurrency->code;

                return $row;
            });
    }

    private function addAgentProductFacts(QueryBuilder $queryBuilder, Agent $agent): void
    {
        $queryBuilder
            ->leftJoinSub($this->agentProductFacts($agent), 'f', 'f.id', 'supplier_products.id')
            ->addSelect('supplier_products.id as supplier_product_id')
            ->selectRaw('max(f.internal_code) as internal_code')
            ->selectRaw('max(f.cbm) as cbm')
            ->selectRaw('max(f.lead_time_days) as lead_time_days')
            ->selectRaw('max(f.moq) as moq')
            ->selectRaw('max(f.availability_status) as availability_status')
            ->selectRaw('max(round('.$this->completenessSql().')) as completeness')
            ->selectRaw($this->completenessChecksSql().' as completeness_checks');
    }

    /**
     * @return array<int, AllowedFilter>
     */
    private function agentProductFilters(): array
    {
        return [
            AllowedFilter::callback('focus', function ($query, $value) {
                match ($value) {
                    'missing_cbm'       => $query->whereRaw('coalesce(f.cbm, 0) = 0'),
                    'missing_lead_time' => $query->whereNull('f.lead_time_days'),
                    'without_family'    => $query->whereNull('f.stock_family_id'),
                    'not_available'     => $query->where('f.availability_status', '!=', 'available'),
                    default             => null,
                };
            }),
            AllowedFilter::callback('supplier', fn ($query, $value) => $query->where('supplier_products.supplier_id', (int) $value)),
            AllowedFilter::callback('family', fn ($query, $value) => $value === 'none'
                ? $query->whereNull('f.stock_family_id')
                : $query->where('f.stock_family_id', (int) $value)),
        ];
    }

    public function tableStructure(Organisation|OrgAgent|OrgSupplier $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null,
                );
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord($parent instanceof Organisation && $parent->type === OrganisationTypeEnum::AGENT ? [__('Product'), __('Products')] : [__('Supplier Product'), __('Supplier Products')]);

            $table->column(key: 'internal_code', label: $this->getParentOrganisationAgent($parent) ? __('Our code') : __('Product code'), canBeHidden: false, sortable: true);

            $table
                ->column(key: 'code', label: __('Supplier code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if (!$parent instanceof OrgSupplier) {
                $table->column(key: 'supplier_name', label: __('Supplier'), canBeHidden: false, sortable: true);
            }

            if ($this->getParentOrganisationAgent($parent)) {
                $table
                    ->selectFilter('focus', [
                        'missing_cbm'       => __('Missing CBM'),
                        'missing_lead_time' => __('Missing lead time'),
                        'not_available'     => __('Not available'),
                        'without_family'    => __('Without family'),
                    ], __('Show'), noFilterOptionLabel: __('All products'))
                    ->column(key: 'cbm', label: __('CBM'), sortable: true, type: 'number')
                    ->column(key: 'lead_time_days', label: __('Lead time'), type: 'number')
                    ->column(key: 'moq', label: __('MOQ'), type: 'number')
                    ->column(key: 'completeness', label: __('Complete'), sortable: true, type: 'number');
            }

            if (!$this->hideProcurementDetails) {
                $table->column(key: 'cost', label: __('Unit cost'), canBeHidden: false, sortable: true, type: 'currency');
                $table->column(key: 'group_cost', label: __('Unit cost').' ('.$parent->group->currency->code.')', type: 'currency');
            }

            if ($parent instanceof Organisation && !$this->getParentOrganisationAgent($parent)) {
                $table->column(key: 'add', label: '', canBeHidden: false);
            }

            $table->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        $this->withTab($this->getOrganisationAgent($organisation) && !$this->hideProcurementDetails ? OrgSupplierProductsTabsEnum::AGENT_TABS : [OrgSupplierProductsTabsEnum::INDEX->value]);

        return $this->handle($organisation, OrgSupplierProductsTabsEnum::INDEX->value);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request)->withTab([OrgSupplierProductsTabsEnum::INDEX->value]);

        return $this->handle($orgAgent, OrgSupplierProductsTabsEnum::INDEX->value);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgSupplier;
        $this->initialisation($organisation, $request)->withTab([OrgSupplierProductsTabsEnum::INDEX->value, OrgSupplierProductsTabsEnum::HISTORY->value]);
        $this->authorizeProcurementRecord($orgSupplier);

        return $this->handle($orgSupplier, OrgSupplierProductsTabsEnum::INDEX->value);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgSupplierInOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inOrgSupplier($organisation, $orgSupplier, $request);
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return OrgSupplierProductsResource::collection($orgSupplierProducts);
    }

    public function htmlResponse(LengthAwarePaginator $orgSupplierProducts, ActionRequest $request): Response
    {
        $title         = $this->parent instanceof Organisation && $this->parent->type === OrganisationTypeEnum::AGENT ? __('Products') : __('Supplier Products');
        $icon          = [
            'icon'  => ['fal', 'fa-box-usd'],
            'title' => $title,
        ];
        $subNavigation = null;
        $afterTitle    = null;
        $iconRight     = null;
        $tabsNavigation = OrgSupplierProductsTabsEnum::navigationOnly([OrgSupplierProductsTabsEnum::INDEX->value]);
        $agent          = $this->hideProcurementDetails ? null : $this->getParentOrganisationAgent($this->parent);
        if ($agent) {
            $tabsNavigation = OrgSupplierProductsTabsEnum::navigationOnly(OrgSupplierProductsTabsEnum::AGENT_TABS);
        }

        if ($this->parent instanceof OrgAgent) {
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Supplier Products'),
            ];
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $afterTitle    = ['label' => __('Supplier Products')];
            $iconRight     = ['icon' => 'fal fa-box-usd'];
        } elseif ($this->parent instanceof OrgSupplier) {
            $title         = $this->parent->supplier->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Supplier Products'),
            ];
            $subNavigation = $this->getOrgSupplierNavigation($this->parent);
            $afterTitle    = ['label' => __('Supplier Products')];
            $iconRight     = ['icon' => 'fal fa-box-usd'];
            $tabsNavigation = OrgSupplierProductsTabsEnum::navigationOnly([OrgSupplierProductsTabsEnum::INDEX->value, OrgSupplierProductsTabsEnum::HISTORY->value]);
        }

        $response = Inertia::render(
            'Procurement/OrgSupplierProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => match (true) {
                    $this->parent instanceof OrgSupplier => '('.$this->parent->supplier->code.') '.__('Supplier Products'),
                    $this->parent instanceof OrgAgent    => '('.$this->parent->agent->organisation->code.') '.__('Supplier Products'),
                    default                              => $title,
                },
                'navigation'  => $this->getParentSiblingsNavigation($this->parent, $request),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $this->parent instanceof OrgSupplier
                        ? [$this->getOrgSupplierPurchaseOrderAction($this->parent)]
                        : [],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $tabsNavigation,
                ],
                OrgSupplierProductsTabsEnum::INDEX->value => $this->tab == OrgSupplierProductsTabsEnum::INDEX->value
                    ? fn () => OrgSupplierProductsResource::collection($orgSupplierProducts)
                    : Inertia::optional(fn () => OrgSupplierProductsResource::collection($orgSupplierProducts)),
                OrgSupplierProductsTabsEnum::DASHBOARD->value => $agent
                    ? ($this->tab == OrgSupplierProductsTabsEnum::DASHBOARD->value
                        ? fn () => GetAgentProductsDashboard::run($agent)
                        : Inertia::optional(fn () => GetAgentProductsDashboard::run($agent)))
                    : null,
                OrgSupplierProductsTabsEnum::SUPPLIERS->value => $agent
                    ? ($this->tab == OrgSupplierProductsTabsEnum::SUPPLIERS->value
                        ? fn () => AgentSuppliersDataQualityResource::collection(IndexAgentSuppliersDataQuality::run($agent, $this->parent, OrgSupplierProductsTabsEnum::SUPPLIERS->value))
                        : Inertia::optional(fn () => AgentSuppliersDataQualityResource::collection(IndexAgentSuppliersDataQuality::run($agent, $this->parent, OrgSupplierProductsTabsEnum::SUPPLIERS->value))))
                    : null,
                'labels_print'  => $this->getLabelsPrint(),
                'copy_products' => $this->getCopyProducts($request),
                'copy_targets'  => Inertia::optional(fn () => $this->getCopyTargets($request)),
                OrgSupplierProductsTabsEnum::HISTORY->value => $this->parent instanceof OrgSupplier && $this->tab == OrgSupplierProductsTabsEnum::HISTORY->value
                    ? fn () => HistoryResource::collection(IndexHistory::run($this->parent, OrgSupplierProductsTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => $this->parent instanceof OrgSupplier
                        ? HistoryResource::collection(IndexHistory::run($this->parent, OrgSupplierProductsTabsEnum::HISTORY->value))
                        : null),
            ],
        )->table($this->tableStructure($this->parent, OrgSupplierProductsTabsEnum::INDEX->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OrgSupplierProductsTabsEnum::HISTORY->value));

        return $agent ? $response->table(IndexAgentSuppliersDataQuality::make()->tableStructure(OrgSupplierProductsTabsEnum::SUPPLIERS->value)) : $response;
    }

    /**
     * @return array{plan_route: array{name: string, parameters: array<string, string>}, pdf_route: array{name: string, parameters: array<string, string>}, ids_parameter: string}
     */
    private function getLabelsPrint(): array
    {
        $routePrefix = $this->organisation->type === OrganisationTypeEnum::AGENT ? 'grp.org.agent' : 'grp.org.procurement';
        $parameters  = ['organisation' => $this->organisation->slug];

        return [
            'plan_route'    => ['name' => "$routePrefix.org_supplier_products.labels.plan", 'parameters' => $parameters],
            'pdf_route'     => ['name' => "$routePrefix.org_supplier_products.labels", 'parameters' => $parameters],
            'ids_parameter' => 'org_supplier_products',
        ];
    }

    private function agentCanCopyProducts(ActionRequest $request): bool
    {
        return $this->parent instanceof OrgSupplier
            && $this->organisationAgentSupplies($this->parent)
            && $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @return array{agent_id: int, agents: array<int, array{id: int, label: string, create_route: array{name: string, parameters: array<int, string>}}>, copy_route: array{name: string, parameters: array<int, int>}}|null
     */
    private function getCopyProducts(ActionRequest $request): ?array
    {
        if (!$this->agentCanCopyProducts($request)) {
            return null;
        }

        return [
            'agent_id'   => $this->parent->supplier->agent_id,
            'agents'     => CopySupplierProductsToSupplier::make()->getAccessibleAgents($request->user())
                ->map(fn (Agent $agent) => [
                    'id'           => $agent->id,
                    'label'        => $agent->organisation->name,
                    'create_route' => [
                        'name'       => 'grp.org.procurement.org_suppliers.create_new',
                        'parameters' => [$agent->organisation->slug],
                    ],
                ])
                ->all(),
            'copy_route' => [
                'name'       => 'grp.models.org.supplier.supplier_products.copy',
                'parameters' => [$this->organisation->id],
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, agent_id: int, label: string, number_products: int}>
     */
    private function getCopyTargets(ActionRequest $request): array
    {
        if (!$this->agentCanCopyProducts($request)) {
            return [];
        }

        return Supplier::whereIn('agent_id', CopySupplierProductsToSupplier::make()->getAccessibleAgents($request->user())->pluck('id'))
            ->where('status', true)
            ->where('id', '!=', $this->parent->supplier_id)
            ->withCount('supplierProducts')
            ->orderBy('code')
            ->get(['id', 'agent_id', 'code', 'name'])
            ->map(fn (Supplier $supplier) => [
                'id'       => $supplier->id,
                'agent_id' => $supplier->agent_id,
                'number_products' => $supplier->supplier_products_count,
                'label'    => $supplier->name && $supplier->name !== $supplier->code ? $supplier->code.' — '.$supplier->name : $supplier->code,
            ])
            ->all();
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumbLabel = Organisation::where('slug', Arr::get($routeParameters, 'organisation'))->value('type') === OrganisationTypeEnum::AGENT ? __('Products') : __('Supplier Products');
        $headCrumb      = function (array $routeParameters = []) use ($headCrumbLabel) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => $headCrumbLabel,
                        'icon'  => 'fal fa-bars',
                        'route' => $routeParameters,
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.procurement.org_supplier_products.index' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_supplier_products.index',
                        'parameters' => Arr::only($routeParameters, 'organisation'),
                    ],
                ),
            ),
            'grp.org.procurement.org_agents.show.supplier_products.index' =>
            array_merge(
                (new ShowOrgAgent())->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.org.procurement.org_suppliers.show.supplier_products.index',
            'grp.org.procurement.org_agents.show.suppliers.show.supplier_products.index' =>
            array_merge(
                (new ShowOrgSupplier())->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            default => [],
        };
    }
}
