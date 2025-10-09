<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 11:48:50 British Summer Time,
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
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
    use WithOrgAgentSubNavigation;
    use WithOrgSupplierSubNavigation;
    private OrgSupplier|OrgAgent|Organisation $parent;

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


        if (class_basename($parent) == 'OrgAgent') {
            $queryBuilder->leftJoin('org_agents', 'org_agents.id', 'org_supplier_products.org_agent_id');
            //$queryBuilder->leftJoin('agents', 'agents.id', 'org_agents.agent_id');

            $queryBuilder->where('org_supplier_products.org_agent_id', $parent->id);
            $queryBuilder->addSelect('org_agents.slug as org_agent_slug');
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $queryBuilder->where('org_supplier_products.org_supplier_id', $parent->id);
        } else {
            $queryBuilder->where('org_supplier_products.organisation_id', $this->organisation->id);
        }


        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'org_supplier_products.slug',
                'supplier_products.code',
                'supplier_products.name'
            ])
            ->leftJoin('org_supplier_product_stats', 'org_supplier_product_stats.org_supplier_product_id', 'org_supplier_products.id')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|OrgAgent|OrgSupplier $parent, array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
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
        return $this->handle($orgAgent);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgSupplier;
        $this->initialisation($organisation, $request);
        return $this->handle($orgSupplier);
    }


    public function jsonResponse(LengthAwarePaginator $orgSupplierProducts): AnonymousResourceCollection
    {
        return OrgSupplierProductsResource::collection($orgSupplierProducts);
    }


    public function htmlResponse(LengthAwarePaginator $orgSupplierProducts, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = __('Supplier products');
        $model = '';
        $icon  = [
            'icon'  => ['fal', 'fa-box-usd'],
            'title' => __('Supplier products')
        ];
        $afterTitle = null;
        $iconRight = null;

        if ($this->parent instanceof OrgAgent) {
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $title = $this->parent->agent->organisation->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Supplier products')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box-usd',
            ];
            $afterTitle = [

                'label'     => __('Supplier Products')
            ];
        } elseif ($this->parent instanceof OrgSupplier) {
            $subNavigation = $this->getOrgSupplierNavigation($this->parent);
            $title = $this->parent->supplier->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Supplier products')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box-usd',
            ];
            $afterTitle = [

                'label'     => __('Supplier Products')
            ];
        }

        return Inertia::render(
            'Procurement/OrgSupplierProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Supplier products'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => OrgSupplierProductsResource::collection($orgSupplierProducts),


            ]
        )->table($this->tableStructure($this->parent));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Supplier products'),
                        'icon'  => 'fal fa-bars'
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
                        'parameters' => Arr::only($routeParameters, 'organisation')
                    ]
                ),
            ),


            'grp.org.procurement.org_agents.show.supplier_products.index' =>
            array_merge(
                (new ShowOrgAgent())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.procurement.org_suppliers.show.supplier_products.index' =>
            array_merge(
                (new ShowOrgSupplier())->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
