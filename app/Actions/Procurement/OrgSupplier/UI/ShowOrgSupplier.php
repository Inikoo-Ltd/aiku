<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Helpers\History\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexOrgSupplierProducts;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplier extends OrgAction
{
    public function handle(OrgSupplier $orgSupplier): OrgSupplier
    {
        return $orgSupplier;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->hasPermissionTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->hasPermissionTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->hasPermissionTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($orgSupplier);
    }

    public function maya(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->maya   =true;
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());
        return $this->handle($orgSupplier);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgSupplier',
            [
                'title'       => __('supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgSupplier, $request),
                    'next'     => $this->getNext($orgSupplier, $request),
                ],
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => 'fal fa-person-dolly',
                            'title' => __('supplier')
                        ],
                    'title'   => $orgSupplier->supplier->name,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.remove',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canEdit && $orgSupplier->owner_type == 'Organisation' ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show.purchase_orders.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                            'label' => __('purchase order')
                        ] : false,
                    ],
                    'meta'    => [
                        [
                            'name'     => trans_choice('Purchases|Sales', $orgSupplier->stats->number_open_purchase_orders),
                            'number'   => $orgSupplier->stats->number_open_purchase_orders,
                            'href'     => [
                                'grp.org.procurement.org_supplier_products.show',
                                $orgSupplier->slug
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-person-dolly',
                                'tooltip' => __('sales')
                            ]
                        ],
                        [
                            'name'     => trans_choice('product|products', $orgSupplier->stats->number_supplier_products),
                            'number'   => $orgSupplier->stats->number_supplier_products,
                            'href'     => [
                                'grp.org.procurement.org_supplier_products.show',
                                $orgSupplier->slug
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-box-usd',
                                'tooltip' => __('products')
                            ]
                        ],
                    ]

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation()
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgSupplierShowcase::run($orgSupplier)
                    : Inertia::lazy(fn () => GetOrgSupplierShowcase::run($orgSupplier)),


                SupplierTabsEnum::SUPPLIER_PRODUCTS->value => $this->tab == SupplierTabsEnum::SUPPLIER_PRODUCTS->value ?
                    fn () => SupplierProductResource::collection(IndexOrgSupplierProducts::run($orgSupplier))
                    : Inertia::lazy(fn () => SupplierProductResource::collection(IndexOrgSupplierProducts::run($orgSupplier))),

                SupplierTabsEnum::PURCHASE_ORDERS->value => $this->tab == SupplierTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplier))
                    : Inertia::lazy(fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplier))),

                SupplierTabsEnum::DELIVERIES->value => $this->tab == SupplierTabsEnum::DELIVERIES->value ?
                    fn () => StockDeliveryResource::collection(IndexStockDeliveries::run($orgSupplier))
                    : Inertia::lazy(fn () => StockDeliveryResource::collection(IndexStockDeliveries::run($orgSupplier))),

                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgSupplier))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgSupplier)))
            ]
        )->table(IndexOrgSupplierProducts::make()->tableStructure($orgSupplier))
            ->table(IndexPurchaseOrders::make()->tableStructure())
            ->table(IndexStockDeliveries::make()->tableStructure())
            ->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value));
    }


    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $orgSupplier = OrgSupplier::where('slug', $routeParameters['orgSupplier'])->first();

        return array_merge(
            (new ShowProcurementDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => [
                                    $routeParameters['organisation'],
                                ]
                            ],
                            'label' => __('Suppliers')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show',
                                'parameters' => [
                                    $routeParameters['organisation'],
                                    $orgSupplier->slug
                                ]
                            ],
                            'label' => $orgSupplier->supplier->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }


    public function jsonResponse(OrgSupplier $orgSupplier): OrgSupplierResource
    {
        return new OrgSupplierResource($orgSupplier);
    }

    public function getPrevious(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        $previous = OrgSupplier::where('slug', '<', $orgSupplier->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgSupplier $orgSupplier, ActionRequest $request): ?array
    {
        $next = OrgSupplier::where('slug', '>', $orgSupplier->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgSupplier $orgSupplier, string $routeName): ?array
    {
        if (!$orgSupplier) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.org_suppliers.show' => [
                'label' => $orgSupplier->supplier->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $orgSupplier->organisation->slug,
                        'orgSupplier'     => $orgSupplier->slug
                    ]

                ]
            ]
        };
    }

}
