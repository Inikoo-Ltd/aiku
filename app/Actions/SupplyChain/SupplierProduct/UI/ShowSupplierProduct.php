<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:57:03 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\InertiaAction;
use App\Enums\UI\SupplyChain\SupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierProduct extends InertiaAction
{
    public function handle(SupplierProduct $supplierProduct): SupplierProduct
    {
        return $supplierProduct;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
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
                'title' => __('supplier product'),
                'breadcrumbs' => $this->getBreadcrumbs($supplierProduct, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation' => [
                    'previous' => $this->getPrevious($supplierProduct, $request),
                    'next' => $this->getNext($supplierProduct, $request),
                ],
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'box-usd'],
                        'title' => __('Agent'),
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
                'supplier' => new SupplierProductResource($supplierProduct),
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => SupplierProductTabsEnum::navigation(),
                ],
                SupplierProductTabsEnum::SHOWCASE->value => $this->tab == SupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierProductShowcase::run($supplierProduct)
                    : Inertia::lazy(fn () => GetSupplierProductShowcase::run($supplierProduct)),
                SupplierProductTabsEnum::HISTORY->value => $this->tab == SupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplierProduct))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($supplierProduct))),

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

                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Supplier Products'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $supplierProduct->name,
                        ],

                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.show' => array_merge(
                IndexSupplierProducts::make()->getBreadcrumbs($routeName, $routeParameters, $supplierProduct->supplier),
                $headCrumb(
                    $supplierProduct,
                    [
                        'index' => [
                            'name' => 'grp.supply-chain.suppliers.supplier_products.index',
                            'parameters' => [],
                        ],
                        'model' => [
                            'name' => 'grp.supply-chain.suppliers.supplier_products.show',
                            'parameters' => [
                                'supplier' => $supplierProduct->supplier->slug,
                                'supplierProduct' => $supplierProduct->slug,
                            ],
                        ],
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function getPrevious(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        $query = SupplierProduct::where('code', '<', $supplierProduct->code);

        $query = match ($request->route()->getName()) {
            'grp.supply-chain.agents.show.supplier_products.show' => $query->where('supplier_products.agent_id', $supplierProduct->supplier_id),
            'grp.supply-chain.agents.show.show.supplier.supplier_products.show',
            'grp.supply-chain.supplier.supplier_products.show' => $query->where('supplier_products.supplier_id', $supplierProduct->supplier_id),

            default => $query
        };

        $previous = $query->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        $query = SupplierProduct::where('code', '>', $supplierProduct->code);

        $query = match ($request->route()->getName()) {
            'grp.supply-chain.agents.show.supplier_products.show' => $query->where('supplier_products.agent_id', $supplierProduct->supplier_id),
            'grp.supply-chain.agents.show.show.supplier.supplier_products.show',
            'grp.supply-chain.suppliers.supplier_products.show' => $query->where('supplier_products.supplier_id', $supplierProduct->supplier_id),

            default => $query
        };

        $next = $query->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?SupplierProduct $supplierProduct, string $routeName): ?array
    {
        if (! $supplierProduct) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.show' => [
                'label' => $supplierProduct->code,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'supplier' => $supplierProduct->supplier->slug,
                        'supplierProduct' => $supplierProduct->slug,
                    ],

                ],
            ],
        };
    }
}
