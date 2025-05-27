<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 16:07:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Catalogue\Product\UI\IndexProductsInOrgStock;
use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Procurement\OrgStockTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgStock extends OrgAction
{
    use WithInventoryAuthorisation;


    private Organisation|StockFamily $parent;

    public function handle(OrgStock $orgStock): OrgStock
    {
        return $orgStock;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockTabsEnum::values());

        return $this->handle($orgStock);
    }

    public function maya(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockTabsEnum::values());

        return $this->handle($orgStock);
    }


    public function htmlResponse(OrgStock $orgStock, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrgStock',
            [
                'title'                           => __('stock'),
                'breadcrumbs'                     => $this->getBreadcrumbs(
                    $orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                      => [
                    'previous' => $this->getPrevious($orgStock, $request),
                    'next'     => $this->getNext($orgStock, $request),
                ],
                'pageHead'                        => [
                    'icon'  => [
                        'title' => __('sku'),
                        'icon'  => 'fal fa-box'
                    ],
                    'model' => __('SKU'),
                    'title' => $orgStock->code,

                ],
                'tabs'                            => [
                    'current'    => $this->tab,
                    'navigation' => OrgStockTabsEnum::navigation()

                ],
                OrgStockTabsEnum::SHOWCASE->value => $this->tab == OrgStockTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgStockShowcase::run($this->warehouse, $orgStock)
                    : Inertia::lazy(fn () => GetOrgStockShowcase::run($this->warehouse, $orgStock)),

                OrgStockTabsEnum::PURCHASE_ORDERS->value => $this->tab == OrgStockTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgStock))
                    : Inertia::lazy(fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgStock))),

                OrgStockTabsEnum::PRODUCTS->value => $this->tab == OrgStockTabsEnum::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexProductsInOrgStock::run($orgStock))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexProductsInOrgStock::run($orgStock))),

                OrgStockTabsEnum::HISTORY->value => $this->tab == OrgStockTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgStock))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgStock)))


            ]
        )
        ->table(IndexProductsInOrgStock::make()->tableStructure(prefix: OrgStockTabsEnum::PRODUCTS->value))
        ->table(IndexPurchaseOrders::make()->tableStructure($orgStock));
    }


    public function jsonResponse(OrgStock $orgStock): OrgStockResource
    {
        return new OrgStockResource($orgStock);
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OrgStock $orgStock, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('SKUs')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $orgStock->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };


        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', =>
            array_merge(
                (new ShowInventoryDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $orgStock,
                    [
                        'index' => [
                            'name'       => preg_replace('/\.show$/', '.index', $routeName),
                            'parameters' => Arr::except($routeParameters, ['orgStock'])
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters

                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show' =>
            array_merge(
                (new ShowStockFamily())->getBreadcrumbs($routeParameters['stockFamily']),
                $headCrumb(
                    $orgStock,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index',
                            'parameters' => [
                                $routeParameters['orgStockFamily']->slug
                            ]
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show',
                            'parameters' => [
                                $routeParameters['orgStockFamily']->slug,
                                $routeParameters['orgStock']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(OrgStock $orgStock, ActionRequest $request): ?array
    {
        $previous = OrgStock::where('code', '<', $orgStock->code)->when(true, function ($query) use ($orgStock, $request) {
            if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show') {
                $query->where('org_stock_family_id', $orgStock->orgStockFamily->id);
            }
        })->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgStock $orgStock, ActionRequest $request): ?array
    {
        $next = OrgStock::where('code', '>', $orgStock->code)->when(true, function ($query) use ($orgStock, $request) {
            if ($request->route()->getName() == 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show') {
                $query->where('org_stock_family_id', $orgStock->orgStockFamily->id);
            }
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgStock $orgStock, string $routeName): ?array
    {
        if (!$orgStock) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
            'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show',
            'grp.org.warehouses.show.inventory.org-stocks.show' => [
                'label' => $orgStock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $orgStock->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                        'orgStock'     => $orgStock->slug
                    ]
                ]
            ],
            'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show' => [
                'label' => $orgStock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stockFamily' => $orgStock->orgStockFamily->slug,
                        'stock'       => $orgStock->slug
                    ]

                ]
            ]
        };
    }
}
