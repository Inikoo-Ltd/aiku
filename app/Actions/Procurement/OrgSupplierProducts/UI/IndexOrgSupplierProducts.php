<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 11:48:50 British Summer Time,
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\UI\Procurement\OrgSupplierProductsTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
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
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;
    use WithOrgSupplierSubNavigation;
    use WithAgentOrganisation;

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
            ->selectRaw('supplier_products.state, count(*) as total')
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
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereAnyWordStartWith('supplier_products.name', $value);
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
            'org_supplier_products.slug',
            'supplier_products.code',
            'supplier_products.name',
            'supplier_products.cost',
            'supplier_products.units_per_carton',
            'currencies.code as currency_code',
        ]);

        if ($organisationAgent) {
            $queryBuilder
                ->leftJoin('organisations', 'org_supplier_products.organisation_id', 'organisations.id')
                ->addSelect(['organisations.name as organisation_name']);
        }

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->allowedSorts(['code', 'name', 'cost'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->withLabelRecord([__('Supplier Product'), __('Supplier Products')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($this->getParentOrganisationAgent($parent)) {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, searchable: true);
            }

            $table->column(key: 'cost', label: __('Cost'), canBeHidden: false, sortable: true, type: 'currency');

            if ($parent instanceof Organisation && !$this->getParentOrganisationAgent($parent)) {
                $table->column(key: 'add', label: '', canBeHidden: false);
            }

            $table->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab([OrgSupplierProductsTabsEnum::INDEX->value]);

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
        $this->initialisation($organisation, $request)->withTab(OrgSupplierProductsTabsEnum::values());

        return $this->handle($orgSupplier, OrgSupplierProductsTabsEnum::INDEX->value);
    }

    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return OrgSupplierProductsResource::collection($orgSupplierProducts);
    }

    public function htmlResponse(LengthAwarePaginator $orgSupplierProducts, ActionRequest $request): Response
    {
        $title         = __('Supplier Products');
        $icon          = [
            'icon'  => ['fal', 'fa-box-usd'],
            'title' => __('Supplier Products'),
        ];
        $subNavigation = null;
        $afterTitle    = null;
        $iconRight     = null;
        $tabsNavigation = OrgSupplierProductsTabsEnum::navigationOnly([OrgSupplierProductsTabsEnum::INDEX->value]);

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
            $tabsNavigation = OrgSupplierProductsTabsEnum::navigation();
        }

        return Inertia::render(
            'Procurement/OrgSupplierProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Supplier Products'),
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
                OrgSupplierProductsTabsEnum::HISTORY->value => $this->parent instanceof OrgSupplier && $this->tab == OrgSupplierProductsTabsEnum::HISTORY->value
                    ? fn () => HistoryResource::collection(IndexHistory::run($this->parent, OrgSupplierProductsTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => $this->parent instanceof OrgSupplier
                        ? HistoryResource::collection(IndexHistory::run($this->parent, OrgSupplierProductsTabsEnum::HISTORY->value))
                        : null),
            ],
        )->table($this->tableStructure($this->parent, OrgSupplierProductsTabsEnum::INDEX->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OrgSupplierProductsTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Supplier Products'),
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
            'grp.org.procurement.org_suppliers.show.supplier_products.index' =>
            array_merge(
                (new ShowOrgSupplier())->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            default => [],
        };
    }
}
