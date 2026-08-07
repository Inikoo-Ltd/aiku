<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:57:03 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\InertiaAction;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\UI\SupplyChain\SupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierProduct extends InertiaAction
{
    use WithBucketNavigation;
    use WithSupplyChainAuthorisation;

    public function handle(SupplierProduct $supplierProduct): SupplierProduct
    {
        return $supplierProduct;
    }

    private function getTabs(): array
    {
        return [
            SupplierProductTabsEnum::SHOWCASE->value,
            SupplierProductTabsEnum::HISTORY->value,
        ];
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab($this->getTabs());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplier(Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab($this->getTabs());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab($this->getTabs());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplierInAgent(Agent $agent, Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab($this->getTabs());

        return $this->handle($supplierProduct);
    }

    public function htmlResponse(SupplierProduct $supplierProduct, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/SupplierProduct',
            [
                'title'       => __('Supplier Product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplierProduct,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplierProduct, $request),
                    'next'     => $this->getNext($supplierProduct, $request),
                ],
                'pageHead'    => [
                    'title' => $supplierProduct->name,
                    'model' => __('Supplier Product'),
                    'icon'  => [
                        'icon'  => ['fal', 'box-usd'],
                        'title' => __('Supplier Product'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierProductTabsEnum::navigationOnly($this->getTabs()),
                ],

                SupplierProductTabsEnum::SHOWCASE->value => $this->tab == SupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierProductShowcase::run($supplierProduct)
                    : Inertia::optional(fn () => GetSupplierProductShowcase::run($supplierProduct)),

                SupplierProductTabsEnum::HISTORY->value => $this->tab == SupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplierProduct))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($supplierProduct))),
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierProductTabsEnum::HISTORY->value));
    }

    public function jsonResponse(SupplierProduct $supplierProduct): SupplierProductResource
    {
        return new SupplierProductResource($supplierProduct);
    }

    public function getBreadcrumbs(SupplierProduct $supplierProduct, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (array $index, array $model) use ($supplierProduct, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $index,
                            'label' => __('Supplier Products'),
                        ],
                        'model' => [
                            'route' => $model,
                            'label' => $supplierProduct->name,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        $supplier = $supplierProduct->supplier;
        $agent    = $supplierProduct->agent;

        return match ($routeName) {
            'grp.supply-chain.supplier_products.show' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.supplier_products.index',
                        'parameters' => [],
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => ['supplierProduct' => $supplierProduct->slug],
                    ],
                ),
            ),
            'grp.supply-chain.suppliers.supplier_products.show' =>
            array_merge(
                ShowSupplier::make()->getBreadcrumbs(
                    $supplier,
                    'grp.supply-chain.suppliers.show',
                    ['supplier' => $supplier->slug]
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.suppliers.supplier_products.index',
                        'parameters' => ['supplier' => $supplier->slug],
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => [
                            'supplier'        => $supplier->slug,
                            'supplierProduct' => $supplierProduct->slug,
                        ],
                    ],
                ),
            ),
            'grp.supply-chain.agents.show.suppliers.supplier_products.show' =>
            array_merge(
                ShowSupplier::make()->getBreadcrumbs(
                    $supplier,
                    'grp.supply-chain.agents.show.suppliers.show',
                    [
                        'agent'    => $agent->slug,
                        'supplier' => $supplier->slug,
                    ]
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.agents.show.suppliers.supplier_products.index',
                        'parameters' => [
                            'agent'    => $agent->slug,
                            'supplier' => $supplier->slug,
                        ],
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => [
                            'agent'           => $agent->slug,
                            'supplier'        => $supplier->slug,
                            'supplierProduct' => $supplierProduct->slug,
                        ],
                    ],
                ),
            ),
            'grp.supply-chain.agents.show.supplier_products.show' =>
            array_merge(
                ShowAgent::make()->getBreadcrumbs($agent, $routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.supply-chain.agents.show.supplier_products.index',
                        'parameters' => ['agent' => $agent->slug],
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => [
                            'agent'           => $agent->slug,
                            'supplierProduct' => $supplierProduct->slug,
                        ],
                    ],
                ),
            ),
            default => [],
        };
    }

    public function getPrevious(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierProductNeighbour($supplierProduct, $request, forward: false), $request->route()->getName());
    }

    public function getNext(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierProductNeighbour($supplierProduct, $request, forward: true), $request->route()->getName());
    }

    private function getSupplierProductNeighbour(SupplierProduct $supplierProduct, ActionRequest $request, bool $forward): ?SupplierProduct
    {
        $query = SupplierProduct::query()->where('supplier_products.group_id', $supplierProduct->group_id);

        match ($request->route()->getName()) {
            'grp.supply-chain.agents.show.supplier_products.show' => $query->where('supplier_products.agent_id', $supplierProduct->agent_id),
            'grp.supply-chain.agents.show.suppliers.supplier_products.show',
            'grp.supply-chain.suppliers.supplier_products.show' => $query->where('supplier_products.supplier_id', $supplierProduct->supplier_id),
            default => $query->when(
                $request->input('bucket') == 'free',
                fn ($query) => $query->whereNull('supplier_products.agent_id')
            )->when(
                $request->input('bucket') == 'in_agents',
                fn ($query) => $query->whereNotNull('supplier_products.agent_id')
            ),
        };

        return $this->getBucketNeighbour(
            query: $query,
            model: $supplierProduct,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code' => 'supplier_products.code',
                'name' => 'supplier_products.name',
            ],
            defaultSort: ['supplier_products.code', false],
            forward: $forward
        );
    }

    private function getNavigation(?SupplierProduct $supplierProduct, string $routeName): ?array
    {
        if (!$supplierProduct) {
            return null;
        }

        $parameters = match ($routeName) {
            'grp.supply-chain.supplier_products.show' => [
                'supplierProduct' => $supplierProduct->slug,
            ],
            'grp.supply-chain.suppliers.supplier_products.show' => [
                'supplier'        => $supplierProduct->supplier->slug,
                'supplierProduct' => $supplierProduct->slug,
            ],
            'grp.supply-chain.agents.show.suppliers.supplier_products.show' => [
                'agent'           => $supplierProduct->agent->slug,
                'supplier'        => $supplierProduct->supplier->slug,
                'supplierProduct' => $supplierProduct->slug,
            ],
            'grp.supply-chain.agents.show.supplier_products.show' => [
                'agent'           => $supplierProduct->agent->slug,
                'supplierProduct' => $supplierProduct->slug,
            ],
            default => null,
        };

        if (!$parameters) {
            return null;
        }

        return [
            'label' => $supplierProduct->code,
            'route' => [
                'name'       => $routeName,
                'parameters' => $parameters,
            ],
        ];
    }
}
