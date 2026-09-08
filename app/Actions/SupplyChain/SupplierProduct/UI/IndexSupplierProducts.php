<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:53:58 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Exports\SupplyChain\SupplierProductTemplateExport;
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
    use WithParentSiblingsNavigation;
    use WithSupplyChainAuthorisation;
    use WithAgentSubNavigation;
    use WithSupplierSubNavigation;

    private Group|Agent|Supplier $scope;

    private string $bucket = 'all';

    protected function getElementGroups(Group|Agent|Supplier $parent): array
    {
        if ($parent instanceof Group && $this->bucket != 'all') {
            $bucketCounts = SupplierProduct::where('group_id', $parent->id)
                ->when($this->bucket == 'free', fn ($query) => $query->whereNull('agent_id'))
                ->when($this->bucket == 'in_agents', fn ($query) => $query->whereNotNull('agent_id'))
                ->selectRaw('state, count(*) as count')
                ->groupBy('state')
                ->pluck('count', 'state')->all();
            $stateCounts = array_map(fn ($state) => $bucketCounts[$state->value] ?? 0, array_column(SupplierProductStateEnum::cases(), null, 'value'));
        } else {
            $stateCounts = SupplierProductStateEnum::count($parent);
        }

        return [
            'state' => [
                'label'    => __('State'),
                'default'  => SupplierProductStateEnum::ACTIVE->value,
                'elements' => array_merge_recursive(
                    SupplierProductStateEnum::labels(),
                    $stateCounts
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('supplier_products.state', $elements);
                },
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
        $queryBuilder->leftJoin('currencies', 'supplier_products.currency_id', 'currencies.id');

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null
            );
        }

        return $queryBuilder
            ->defaultSort('supplier_products.code')
            ->select([
                'supplier_products.id',
                'supplier_products.code',
                'supplier_products.slug',
                'supplier_products.name',
                'supplier_products.cost',
                'currencies.code as currency_code',
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
            ->allowedSorts(['code', 'name', 'cost'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Agent|Supplier|null $parent = null, ?array $modelOperations = null, $prefix = null): Closure
    {
        $parent ??= $this->scope ?? group();

        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
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
                    default: $elementGroup['default'] ?? null
                );
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier Product'), __('Supplier Products')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'cost', label: __('Cost'), canBeHidden: false, sortable: true, type: 'currency')
                ->defaultSort('code');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inAgent(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $agent;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($agent);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplierInAgent(Agent $agent, Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $supplier;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($supplier);
    }

    public function inAgents(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'in_agents';
        $this->scope  = app('group');
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->scope = $supplier;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($supplier);
    }

    public function inOverview(ActionRequest $request): LengthAwarePaginator
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

    public function jsonResponse(LengthAwarePaginator $supplier_products): AnonymousResourceCollection
    {
        return SupplierProductsResource::collection($supplier_products);
    }

    public function htmlResponse(LengthAwarePaginator $supplier_products, ActionRequest $request): Response
    {
        $title         = match ($this->bucket) {
            'free'       => __('Free Supplier Products'),
            'in_agents'  => __('Agents Supplier Products'),
            default      => __('Supplier Products'),
        };
        $icon          = [
            'icon'  => ['fal', 'fa-box-usd'],
            'title' => __('Supplier Products'),
        ];
        $subNavigation = null;

        if ($this->scope instanceof Group) {
            $subNavigation = $this->getSupplierProductsSubNavigation();
        }

        if ($this->scope instanceof Agent) {
            $title         = $this->scope->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Supplier Products'),
            ];
            $subNavigation = $this->getAgentNavigation($this->scope);
            $afterTitle    = ['label' => __('Supplier Products')];
            $iconRight     = ['icon' => 'fal fa-box-usd'];
        } elseif ($this->scope instanceof Supplier) {
            $title         = $this->scope->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Supplier Products'),
            ];
            $subNavigation = $this->getSupplierNavigation($this->scope);
            $afterTitle    = ['label' => __('Supplier Products')];
            $iconRight     = ['icon' => 'fal fa-box-usd'];
            $actions       = [
                [
                    'type'    => 'button',
                    'style'   => 'create',
                    'tooltip' => __('New Supplier Product'),
                    'label'   => __('New Supplier Product'),
                    'route'   => [
                        'name'       => 'grp.supply-chain.suppliers.supplier_products.create',
                        'parameters' => $request->route()->originalParameters(),
                    ],
                ],
            ];
            $spreadsheetRoutes = [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.$this->group->id,
                'required_fields' => array_map(
                    fn ($heading) => strtolower(str_replace(' ', '_', trim($heading))),
                    SupplierProductTemplateExport::HEADINGS
                ),
                'template'        => [
                    'label' => __('Download template (.xlsx)'),
                ],
                'route'           => [
                    'upload'   => [
                        'name'       => 'grp.models.supplier.supplier-product.import',
                        'parameters' => [
                            'supplier' => $this->scope->id,
                        ],
                    ],
                    'download' => [
                        'name'       => 'grp.supply-chain.suppliers.supplier_products.uploads.templates',
                        'parameters' => [
                            'supplier' => $this->scope->slug,
                        ],
                    ],
                ],
            ];
        }

        return Inertia::render(
            'SupplyChain/SupplierProducts',
            [
                'breadcrumbs'       => $this->getBreadcrumbs(
                    $this->scope,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'navigation'        => $this->getParentSiblingsNavigation($this->scope, $request),
                'title'             => $title,
                'pageHead'          => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle ?? null,
                    'iconRight'     => $iconRight ?? null,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions ?? null,
                ],
                'upload_spreadsheet' => $spreadsheetRoutes ?? null,
                'data'               => SupplierProductsResource::collection($supplier_products),
            ],
        )->table($this->tableStructure($this->scope));
    }

    public function getBreadcrumbs(Group|Agent|Supplier $scope, string $routeName, array $routeParameters): array
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
            'grp.supply-chain.supplier_products.index',
            'grp.supply-chain.supplier_products.free',
            'grp.supply-chain.supplier_products.in_agents' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        null,
                    ],
                ),
            ),
            'grp.supply-chain.suppliers.supplier_products.index' =>
            array_merge(
                ShowSupplier::make()->getBreadcrumbs($scope, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.suppliers.supplier_products.index',
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.supply-chain.agents.show.supplier_products.index' =>
            array_merge(
                ShowAgent::make()->getBreadcrumbs($scope, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.agents.show.supplier_products.index',
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.overview.procurement.supplier-products.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
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

    public function getSupplierProductsSubNavigation(): array
    {
        return [
            [
                'label'  => __('Free'),
                'root'   => 'grp.supply-chain.supplier_products.free',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.free',
                    'parameters' => [],
                ],
                'number' => $this->group->supplyChainStats->number_independent_supplier_products,
            ],
            [
                'label'  => __('Agents'),
                'root'   => 'grp.supply-chain.supplier_products.in_agents',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.in_agents',
                    'parameters' => [],
                ],
                'number' => $this->group->supplyChainStats->number_supplier_products_in_agents,
            ],
            [
                'label'  => __('All'),
                'icon'   => 'fal fa-bars',
                'align'  => 'right',
                'root'   => 'grp.supply-chain.supplier_products.index',
                'route'  => [
                    'name'       => 'grp.supply-chain.supplier_products.index',
                    'parameters' => [],
                ],
                'number' => $this->group->supplyChainStats->number_supplier_products,
            ],
        ];
    }
}
