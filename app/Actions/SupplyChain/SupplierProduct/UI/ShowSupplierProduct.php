<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:57:03 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\InertiaAction;
use App\Enums\UI\SupplyChain\SupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\SupplyChain\Supplier;
use App\Actions\Traits\UI\WithBucketNavigation;
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


    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(SupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    public function inSupplier(Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(SupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function htmlResponse(SupplierProduct $supplierProduct, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/SupplierProduct',
            [
                'title'       => __('supplier product'),
                'breadcrumbs' => $this->getBreadcrumbs($supplierProduct, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplierProduct, $request),
                    'next'     => $this->getNext($supplierProduct, $request),
                ],
                'pageHead'    => [
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'box-usd'],
                            'title' => __('Agent')
                        ],
                    'title' => $supplierProduct->name,
                    /*
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    */
                ],
                'supplier'    => new SupplierProductResource($supplierProduct),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierProductTabsEnum::navigation()
                ],
                SupplierProductTabsEnum::SHOWCASE->value => $this->tab == SupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierProductShowcase::run($supplierProduct)
                    : Inertia::optional(fn () => GetSupplierProductShowcase::run($supplierProduct)),
                SupplierProductTabsEnum::HISTORY->value => $this->tab == SupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplierProduct))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($supplierProduct)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierProductTabsEnum::HISTORY->value));
    }


    public function jsonResponse(SupplierProduct $supplierProduct): SupplierProductResource
    {
        return new SupplierProductResource($supplierProduct);
    }

    public function getBreadcrumbs(SupplierProduct $supplierProduct, string $routeName, array $routeParameters, string $suffix = ''): array
    {

        $headCrumb = function (SupplierProduct $supplierProduct, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Supplier Products')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $supplierProduct->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.show' =>
            array_merge(
                IndexSupplierProducts::make()->getBreadcrumbs($routeName, $routeParameters, $supplierProduct->supplier),
                $headCrumb(
                    $supplierProduct,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.suppliers.supplier_products.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.suppliers.supplier_products.show',
                            'parameters' => [
                                'supplier' => $supplierProduct->supplier->slug,
                                'supplierProduct' => $supplierProduct->slug
                                ]
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
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

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.show' => [
                'label' => $supplierProduct->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'supplier'        => $supplierProduct->supplier->slug,
                        'supplierProduct' => $supplierProduct->slug
                    ]

                ]
            ],
            'grp.supply-chain.supplier_products.show' => [
                'label' => $supplierProduct->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'supplierProduct' => $supplierProduct->slug
                    ]

                ]
            ],
            default => null,
        };
    }
}
