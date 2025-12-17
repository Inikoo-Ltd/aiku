<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 May 2023 17:08:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Marketplace\SupplierProduct\UI;

use App\Actions\InertiaAction;
use App\Actions\Procurement\Marketplace\Supplier\UI\ShowMarketplaceSupplier;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\MarketplaceSupplierProductResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMarketplaceSupplierProducts extends InertiaAction
{
    public function handle(Group|Agent|Supplier|Organisation $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(SupplierProduct::class);
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select(['supplier_products.code', 'supplier_products.slug', 'supplier_products.name'])
            ->leftJoin('supplier_product_stats', 'supplier_product_stats.supplier_product_id', 'supplier_products.id')
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'Agent') {
                    $query->leftJoin('agents', 'supplier_products.agent_id', 'agents.id');
                    $query->addSelect('agents.slug as agent_slug');
                    $query->where('supplier_products.agent_id', $parent->id);
                } elseif (class_basename($parent) == 'Group') {
                    $query->where('supplier_products.group_id', $parent->id);
                } elseif (class_basename($parent) == 'Supplier') {
                    $query->leftJoin('suppliers', 'supplier_products.supplier_id', 'suppliers.id');
                    if ($parent->agent) {
                        $query->leftJoin('agents', 'supplier_products.agent_id', 'agents.id');
                        $query->addSelect('agents.slug as agent_slug');
                    }


                    $query->addSelect('suppliers.slug as supplier_slug');
                    $query->where('supplier_products.supplier_id', $parent->id);
                }
            })
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->authTo('procurement.view')
            );
    }

    public function asController(Organisation $organisation): LengthAwarePaginator
    {
        return $this->handle($organisation);
    }

    public function inAgent(Agent $agent): LengthAwarePaginator
    {
        $this->validateAttributes();

        return $this->handle($agent);
    }

    public function inSupplier(Supplier $supplier): LengthAwarePaginator
    {
        $this->validateAttributes();

        return $this->handle($supplier);
    }

    public function jsonResponse(LengthAwarePaginator $supplier_products): AnonymousResourceCollection
    {
        return MarketplaceSupplierProductResource::collection($supplier_products);
    }


    public function htmlResponse(LengthAwarePaginator $supplier_products, ActionRequest $request): Response
    {

        return Inertia::render(
            'Procurement/MarketplaceSupplierProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("supplier product's marketplaces"),
                'pageHead'    => [
                    'title'  => __("supplier product's marketplaces"),
                    'create' => $this->canEdit && $request->route()->getName() == 'grp.org.procurement.marketplace.org_supplier_products.index' ? [
                        'route' => [
                            'name'       => 'grp.org.procurement.marketplace.org_supplier_products.create',
                            'parameters' => array_values($request->route()->originalParameters())
                        ],
                        'label' => __('agent')
                    ] : false,
                ],

                'data'        => MarketplaceSupplierProductResource::collection($supplier_products),


            ]
        )->table($this->tableStructure());
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Supplier products marketplaces'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.procurement.marketplace.org_supplier_products.index' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.org.procurement.marketplace.org_supplier_products.index',
                        null
                    ]
                ),
            ),
            'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.index' =>
            array_merge(
                (new ShowMarketplaceSupplier())->getBreadcrumbs(
                    'grp.org.procurement.marketplace.org_suppliers.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.index',
                        'parameters' =>
                            [
                                $routeParameters['supplier']->slug
                            ]
                    ]
                )
            ),

            'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.index' =>
            array_merge(
                (new \App\Actions\SupplyChain\Agent\UI\ShowAgent())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.index',
                        'parameters' =>
                            [
                                $routeParameters['agent']->slug
                            ]
                    ]
                )
            ),
            default => []
        };
    }
}
