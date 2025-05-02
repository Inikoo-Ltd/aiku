<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 16:07:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Inventory\UI\ShowAgentInventoryDashboard;
use App\Actions\OrgAction;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Http\Resources\SupplyChain\SupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\SupplierProduct;
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

class IndexAgentSupplierProducts extends OrgAction
{
    protected function getElementGroups(Agent $agent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    SupplierProductStateEnum::labels(),
                    SupplierProductStateEnum::count($agent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('supplier_products.state', $elements);
                }
            ],


        ];
    }

    public function handle(Agent $agent, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->leftJoin('agents', 'agents.id', 'supplier_products.agent_id');
        $queryBuilder->where('supplier_products.agent_id', $agent->id);
        $queryBuilder->addSelect('agents.slug as agent_slug');
        $queryBuilder->leftJoin('supplier_product_stats', 'supplier_product_stats.supplier_product_id', 'supplier_products.id');


        foreach ($this->getElementGroups($agent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'supplier_products.id',
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.name'
            ])
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Agent $agent, array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $agent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($agent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation->agent);
    }


    public function jsonResponse(LengthAwarePaginator $supplier_products): AnonymousResourceCollection
    {
        return SupplierProductsResource::collection($supplier_products);
    }

    public function htmlResponse(LengthAwarePaginator $supplier_products, ActionRequest $request): Response
    {
        $organisation = $request->route()->parameters()['organisation'];
        $agent        = $organisation->agent;


        $subNavigation = null;

        $actions = null;


        $title      = 'SKUs';
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-people-arrows'],
            'title' => __('supplier products')
        ];
        $iconRight  = [
            'icon' => 'fal fa-box-usd',
        ];
        $afterTitle = [

            'label' => __('Supplier Products')
        ];


        return Inertia::render(
            'SupplyChain/SupplierProducts',
            [
                'breadcrumbs'        => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'              => __('SKUs'),
                'pageHead'           => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'upload_spreadsheet' => null,
                'data'               => SupplierProductsResource::collection($supplier_products),
            ]
        )->table($this->tableStructure($agent));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('SKUs'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.agent_inventory.supplier_products.index' =>
            array_merge(
                ShowAgentInventoryDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'warehouse'])),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
