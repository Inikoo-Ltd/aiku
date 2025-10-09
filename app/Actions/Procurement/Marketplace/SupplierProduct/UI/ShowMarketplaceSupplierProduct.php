<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 May 2023 20:15:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Marketplace\SupplierProduct\UI;

use App\Actions\InertiaAction;
use App\Actions\Procurement\Marketplace\Supplier\UI\ShowMarketplaceSupplier;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\MarketplaceSupplierProductTabsEnum;
use App\Http\Resources\Procurement\MarketplaceSupplierProductResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMarketplaceSupplierProduct extends InertiaAction
{
    public function handle(SupplierProduct $supplierProduct): SupplierProduct
    {
        return $supplierProduct;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('procurement.org_suppliers.edit');

        return $request->user()->authTo("procurement.view");
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplierInAgent(Agent $agent, Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplier(Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierProductTabsEnum::values());

        return $this->handle($supplierProduct);
    }

    public function getOrderNotes(): array
    {
        return [
            "note_list" => [
                [
                    "label"       => __("Delivery Instructions"),
                    "note"        => '',
                    "information" => __("This note is from the customer. Will be printed in the shipping label."),
                    "editable"    => true,
                    "bgColor"     => "#38bdf8",
                    "field"       => "shipping_notes"
                ],
                [
                    "label"       => __("Customer"),
                    "note"        => '',
                    "information" => __("This note is from customer in the platform. Not editable."),
                    "editable"    => false,
                    "bgColor"     => "#FF7DBD",
                    "field"       => "customer_notes"
                ],
                [
                    "label"       => __("Public"),
                    "note"        =>  '',
                    "information" => __("This note will be visible to public, both staff and the customer can see."),
                    "editable"    => true,
                    "bgColor"     => "#94DB84",
                    "field"       => "public_notes"
                ],
                [
                    "label"       => __("Private"),
                    "note"        => '',
                    "information" => __("This note is only visible to staff members. You can communicate each other about the order."),
                    "editable"    => true,
                    "bgColor"     => "#FCF4A3",
                    "field"       => "internal_notes"
                ]
            ]
        ];
    }

    public function htmlResponse(SupplierProduct $supplierProduct, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/MarketplaceSupplierProduct',
            [
                'title'       => __('supplier product marketplaces'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplierProduct, $request),
                    'next'     => $this->getNext($supplierProduct, $request),
                ],
                'notes'                       => $this->getOrderNotes(),
                'pageHead'    => [
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'box-usd'],
                            'title' => __('Supplier product marketplaces')
                        ],
                    'title' => $supplierProduct->name,

                    'edit' => $this->canEdit &&  $request->route()->getName() == 'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show.org_supplier_products.edit' ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,

                ],
                'tabs'                                     => [
                    'current'    => $this->tab,
                    'navigation' => MarketplaceSupplierProductTabsEnum::navigation()
                ],

            ]
        );
    }


    public function jsonResponse(SupplierProduct $supplierProduct): MarketplaceSupplierProductResource
    {
        return new MarketplaceSupplierProductResource($supplierProduct);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (SupplierProduct $supplierProduct, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Supplier product marketplaces')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $supplierProduct->code,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        //        dd($routeName);
        return match ($routeName) {
            'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.show' => array_merge(
                (new ShowMarketplaceSupplier())->getBreadcrumbs(
                    'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.show',
                    ['supplier' => $routeParameters['supplier']]
                ),
                $headCrumb(
                    $routeParameters['supplierProduct'],
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.index',
                            'parameters' => [
                                $routeParameters['supplier']->slug
                            ]
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.show',
                            'parameters' => [
                                $routeParameters['supplier']->slug,
                                $routeParameters['supplierProduct']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),


            'grp.org.procurement.marketplace.org_supplier_products.show' => array_merge(
                (new ShowProcurementDashboard())->getBreadcrumbs(),
                $headCrumb(
                    $routeParameters['supplierProduct'],
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.marketplace.org_supplier_products.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.marketplace.org_supplier_products.show',
                            'parameters' => [$routeParameters['supplierProduct']->slug]
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.show' => array_merge(
                (new \App\Actions\SupplyChain\Agent\UI\ShowAgent())->getBreadcrumbs(
                    ['agent' => $routeParameters['agent']]
                ),
                $headCrumb(
                    $routeParameters['supplierProduct'],
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.index',
                            'parameters' => [
                                $routeParameters['agent']->slug
                            ]
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.show',
                            'parameters' => [
                                $routeParameters['agent']->slug,
                                $routeParameters['supplierProduct']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }

    public function getPrevious(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        $query = SupplierProduct::where('code', '<', $supplierProduct->code);

        $query = match ($request->route()->getName()) {
            'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.show' => $query->where('supplier_products.agent_id', $request->route()->originalParameters()['agent']->id),
            'grp.org.procurement.marketplace.org_agents.show.show.supplier.org_supplier_products.show',
            'grp.org.procurement.marketplace.supplier.org_supplier_products.show' => $query->where('supplier_products.supplier_id', $request->route()->originalParameters()['supplier']->id),

            default => $query
        };

        $previous = $query->orderBy('code', 'desc')->first();


        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(SupplierProduct $supplierProduct, ActionRequest $request): ?array
    {
        $query = SupplierProduct::where('code', '>', $supplierProduct->code);

        $query = match ($request->route()->getName()) {
            'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.show' => $query->where('supplier_products.agent_id', $request->route()->originalParameters()['agent']->id),
            'grp.org.procurement.marketplace.org_agents.show.show.supplier.org_supplier_products.show',
            'grp.org.procurement.marketplace.supplier.org_supplier_products.show' => $query->where('supplier_products.supplier_id', $request->route()->originalParameters()['supplier']->id),

            default => $query
        };

        $next = $query->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?SupplierProduct $supplierProduct, string $routeName): ?array
    {
        if (!$supplierProduct) {
            return null;
        }


        return match ($routeName) {
            'grp.org.procurement.marketplace.org_supplier_products.show' => [
                'label' => $supplierProduct->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'supplierProduct' => $supplierProduct->slug
                    ]

                ]
            ],
            'grp.org.procurement.marketplace.org_agents.show.org_supplier_products.show' => [
                'label' => $supplierProduct->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'agent'           => $supplierProduct->agent->slug,
                        'supplierProduct' => $supplierProduct->slug
                    ]

                ]
            ]
        };
    }
}
