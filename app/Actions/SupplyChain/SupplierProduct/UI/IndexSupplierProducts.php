<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:53:58 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Http\Resources\SupplyChain\SupplierProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSupplierProducts extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithAgentSubNavigation;
    use WithSupplierSubNavigation;
    private Group|Agent|Supplier $scope;

    private string $bucket = 'all';

    protected function getElementGroups(Group|Agent|Supplier $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    SupplierProductStateEnum::labels(),
                    SupplierProductStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('supplier_products.state', $elements);
                }
            ],


        ];
    }

    public function handle(Group|Agent|Supplier $parent, $prefix = null): LengthAwarePaginator
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


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
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
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.name'
            ])
            ->leftJoin('supplier_product_stats', 'supplier_product_stats.supplier_product_id', 'supplier_products.id')
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'Agent') {
                    $query->leftJoin('agents', 'agents.id', 'supplier_products.agent_id');
                    $query->where('supplier_products.agent_id', $parent->id);
                    $query->addSelect('agents.slug as agent_slug');
                } elseif (class_basename($parent) == 'Supplier') {
                    $query->where('supplier_products.supplier_id', $parent->id);
                } else {
                    $query->where('supplier_products.group_id', $this->group->id);
                    if ($this->bucket == 'free') {
                        $query->whereNull('supplier_products.agent_id');
                    } elseif ($this->bucket == 'in_agents') {
                        $query->whereNotNull('supplier_products.agent_id');
                    }
                }
            })
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier Product'), __('Supplier Products')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function inAgent(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $agent;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($agent);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $supplier;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($supplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplierInAgent(Agent $agent, Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $supplier;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($supplier);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function free(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'free';
        $this->scope  = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inAgents(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_agents';
        $this->scope  = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inOverview(ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function jsonResponse(LengthAwarePaginator $supplier_products): AnonymousResourceCollection
    {
        return SupplierProductsResource::collection($supplier_products);
    }

    public function htmlResponse(LengthAwarePaginator $supplier_products, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title = match ($this->bucket) {
            'free' => __('Free Supplier Products'),
            'in_agents' => __('Agents Supplier Products'),
            default => __('Supplier Products')
        };
        if ($this->scope instanceof Group) {
            $subNavigation = $this->getSupplierProductsSubNavigation();
        }
        $model = '';
        $icon  = [
            'icon'  => ['fal', 'fa-box-usd'],
            'title' => __('Supplier Products')
        ];
        $afterTitle = null;
        $iconRight = null;
        $actions = null;
        $attachRoutes = null;

        if ($this->scope instanceof Agent) {
            $subNavigation = $this->getAgentNavigation($this->scope);
            $title = $this->scope->organisation->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Supplier Products')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box-usd',
            ];
            $afterTitle = [

                'label'     => __('Supplier Products')
            ];
        } elseif ($this->scope instanceof Supplier) {
            $subNavigation = $this->getSupplierNavigation($this->scope);
            $title = $this->scope->name;
            $model = '';
            $icon  = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Supplier Products')
            ];
            $iconRight    = [
                'icon' => 'fal fa-box-usd',
            ];
            $afterTitle = [

                'label'     => __('Supplier Products')
            ];
            $actions = [
                [
                    'type'    =>    'button',
                                    'style'   => 'create',
                                    'tooltip' => __('New Supplier Product'),
                                    'label'   => __('New Supplier Product'),
                                    'route'   => [
                                        'name'       => 'grp.supply-chain.suppliers.supplier_products.create',
                                        'parameters' => $request->route()->originalParameters()
                                    ]
                ]
            ];
            //'grp.models.supplier.supplier-product.import' import route
            $spreadsheetRoute = [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.$this->group->id,
                'required_fields' => ["id:_supplier_part_key", "supplier's_product_code", "units_per_sko", "skos_per_carton", "carton_cbm", "unit_cost", "availability", "supplier's_unit_description"],
                'route'           => [
                    'upload'   => [
                        'name'       => 'grp.models.supplier.supplier-product.import',
                        'parameters' => [
                            'supplier' => $this->scope->id
                        ]
                    ],
                ],
            ];
        }
        return Inertia::render(
            'SupplyChain/SupplierProducts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->scope
                ),
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
                'upload_spreadsheet' => $spreadsheetRoute ?? null,
                'data'        => SupplierProductsResource::collection($supplier_products),
            ]
        )->table($this->tableStructure());
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, Group|Agent|Supplier $scope): array
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
            'grp.supply-chain.supplier_products.index',
            'grp.supply-chain.supplier_products.free',
            'grp.supply-chain.supplier_products.in_agents' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        null
                    ]
                ),
            ),
            'grp.supply-chain.suppliers.supplier_products.index' =>
            array_merge(
                ShowSupplier::make()->getBreadcrumbs($scope, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name' => 'grp.supply-chain.suppliers.supplier_products.index',
                        'parameters' => $routeParameters
                    ]
                ),
            ),

            'grp.supply-chain.agents.show.supplier_products.index' =>
            array_merge(
                ShowAgent::make()->getBreadcrumbs($scope, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.agents.show.supplier_products.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.overview.procurement.supplier-products.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }

    public function getSupplierProductsSubNavigation(): array
    {
        return [
            [
                'label'  => __('Free'),
                'root'   => 'grp.supply-chain.supplier_products.free',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.free',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_independent_supplier_products
            ],
            [
                'label'  => __('Agents'),
                'root'   => 'grp.supply-chain.supplier_products.in_agents',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.in_agents',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_supplier_products_in_agents
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'root'   => 'grp.supply-chain.supplier_products.index',
                'align'  => 'right',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.index',
                    'parameters' => []
                ],
                'number' => $this->group->supplyChainStats->number_supplier_products
            ],
        ];
    }
}
