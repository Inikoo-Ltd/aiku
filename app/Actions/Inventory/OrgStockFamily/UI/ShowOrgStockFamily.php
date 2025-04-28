<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 10:14:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Inventory\OrgStockFamilyTabsEnum;
use App\Http\Resources\Goods\StockFamilyResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Inventory\OrgStocksResource;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgStockFamily extends OrgAction
{
    use WithInventoryAuthorisation;

    public function handle(OrgStockFamily $orgStockFamily): OrgStockFamily
    {
        return $orgStockFamily;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, ActionRequest $request): OrgStockFamily
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockFamilyTabsEnum::values());

        return $this->handle($orgStockFamily);
    }

    public function maya(Organisation $organisation, OrgStockFamily $orgStockFamily, ActionRequest $request): OrgStockFamily
    {
        $this->maya = true;
        $this->initialisation($organisation, $request)->withTab(OrgStockFamilyTabsEnum::values());

        return $this->handle($orgStockFamily);
    }

    public function htmlResponse(OrgStockFamily $orgStockFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrgStockFamily',
            [
                'title'       => __('stock family'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgStockFamily, $request),
                    'next'     => $this->getNext($orgStockFamily, $request),
                ],
                'pageHead'    => [
                   // 'model'   => __('stock family'),
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-boxes-alt'],
                            'title' => __('stock family')
                        ],
                    'title'       => $orgStockFamily->name,
                   'afterTitle'   => [
                       'label'     => $orgStockFamily->code,
                       'tooltip'   => __('reference')
                   ],
                    'meta'    => [
                        [
                            'name'     => trans_choice('stock | stocks', $orgStockFamily->stats->number_org_stocks),
                            'number'   => $orgStockFamily->stats->number_org_stocks,
                            'route'     => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-box',
                                'tooltip' => __('stocks')
                            ]
                        ],
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgStockFamilyTabsEnum::navigation()

                ],

                OrgStockFamilyTabsEnum::SHOWCASE->value => $this->tab == OrgStockFamilyTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgStockFamilyShowcase::run($orgStockFamily)
                    : Inertia::lazy(fn () => GetOrgStockFamilyShowcase::run($orgStockFamily)),
                OrgStockFamilyTabsEnum::ORG_STOCKS->value   => $this->tab == OrgStockFamilyTabsEnum::ORG_STOCKS->value
                    ?
                    fn () => OrgStocksResource::collection(
                        IndexOrgStocks::run(
                            parent: $orgStockFamily,
                            prefix: OrgStockFamilyTabsEnum::ORG_STOCKS->value,
                            bucket: 'all'
                        )
                    )
                    : Inertia::lazy(fn () => OrgStocksResource::collection(
                        IndexOrgStocks::run(
                            parent: $orgStockFamily,
                            prefix: OrgStockFamilyTabsEnum::ORG_STOCKS->value,
                            bucket: 'all'
                        )
                    )),
                OrgStockFamilyTabsEnum::HISTORY->value  => $this->tab == OrgStockFamilyTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgStockFamily->stockFamily))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgStockFamily->stockFamily)))
            ]
        )->table(
            IndexOrgStocks::make()->tableStructure(
                parent: $orgStockFamily,
                prefix: OrgStockFamilyTabsEnum::ORG_STOCKS->value,
            )
        )->table(IndexHistory::make()->tableStructure(prefix: OrgStockFamilyTabsEnum::HISTORY->value));
    }


    public function jsonResponse(OrgStockFamily $orgStockFamily): StockFamilyResource
    {
        return new StockFamilyResource($orgStockFamily);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        /** @var OrgStockFamily $orgStockFamily */
        $orgStockFamily = OrgStockFamily::where('slug', $routeParameters['orgStockFamily'])->firstOrFail();

        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.index',
                                'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                            ],
                            'label' => __('SKUs families'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.show',
                                'parameters' => [$this->organisation->slug, $this->warehouse->slug, $orgStockFamily->slug]
                            ],
                            'label' => $orgStockFamily->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(OrgStockFamily $orgStockFamily, ActionRequest $request): ?array
    {
        $previous = OrgStockFamily::where('code', '<', $orgStockFamily->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgStockFamily $orgStockFamily, ActionRequest $request): ?array
    {
        $next = OrgStockFamily::where('code', '>', $orgStockFamily->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgStockFamily $orgStockFamily, string $routeName): ?array
    {
        if (!$orgStockFamily) {
            return null;
        }

        return match ($routeName) {
            'grp.org.warehouses.show.inventory.org_stock_families.show' => [
                'label' => $orgStockFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'   => $this->organisation->slug,
                        'warehouse'      => $this->warehouse->slug,
                        'orgStockFamily' => $orgStockFamily->slug
                    ]

                ]
            ]
        };
    }
}
