<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\OrgSupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplierProduct extends OrgAction
{
    private OrgAgent|Organisation|OrgSupplier $parent;

    public function handle(OrgSupplierProduct $orgSupplierProduct): OrgSupplierProduct
    {
        return $orgSupplierProduct;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplierProduct);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplierProduct);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->parent = $orgSupplier;
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplierProduct);
    }

    public function htmlResponse(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgSupplierProduct',
            [
                'title'                                              => __('supplier product'),
                'breadcrumbs'                                        => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                // 'navigation'                                         => [
                //     'previous' => $this->getPrevious($orgSupplierProduct, $request),
                //     'next'     => $this->getNext($orgSupplierProduct, $request),
                // ],
                'pageHead'                                           => [
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'box-usd'],
                            'title' => __('Agent')
                        ],
                    'title' => $orgSupplierProduct->supplierProduct->name,
                ],
                'supplier'                                           => new SupplierProductResource($orgSupplierProduct),
                'tabs'                                               => [
                    'current'    => $this->tab,
                    'navigation' => OrgSupplierProductTabsEnum::navigation()
                ],
                OrgSupplierProductTabsEnum::SHOWCASE->value          => $this->tab == OrgSupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgSupplierProductShowcase::run($orgSupplierProduct)
                    : Inertia::lazy(fn () => GetOrgSupplierProductShowcase::run($orgSupplierProduct)),
                // OrgSupplierProductTabsEnum::SUPPLIER_PRODUCTS->value => $this->tab == OrgSupplierProductTabsEnum::SUPPLIER_PRODUCTS->value ?
                //     fn () => SupplierProductResource::collection(IndexOrgSupplierProducts::run($orgSupplierProduct))
                //     : Inertia::lazy(fn () => SupplierProductResource::collection(IndexOrgSupplierProducts::run($orgSupplierProduct))),

                OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value => $this->tab == OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))
                    : Inertia::lazy(fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))),

                OrgSupplierProductTabsEnum::HISTORY->value => $this->tab == OrgSupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct)))

            ]
        )
        // ->table(
        //     IndexOrgSupplierProducts::make()->tableStructure(
        //         $this->parent,
        //         prefix: OrgSupplierProductTabsEnum::SUPPLIER_PRODUCTS->value
        //     )
        // )
            ->table(IndexPurchaseOrders::make()->tableStructure(parent:$orgSupplierProduct, prefix: OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OrgSupplierProductTabsEnum::HISTORY->value));
    }


    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (OrgSupplierProduct $orgSupplierProduct, array $routeParameters, string $suffix) {
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
                            'label' => $orgSupplierProduct->slug,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $orgSupplierProduct = OrgSupplierProduct::where('slug', $routeParameters['orgSupplierProduct'])->firstOrFail();

        return match ($routeName) {
            'grp.org.procurement.org_supplier_products.show' =>
                array_merge(
                    (new ShowProcurementDashboard())->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                    $headCrumb(
                        $orgSupplierProduct,
                        [
                            'index' => [
                                'name'       => 'grp.org.procurement.org_supplier_products.index',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.org.procurement.org_supplier_products.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    )
                ),
            'grp.org.procurement.org_agents.show.supplier_products.show' =>
                array_merge(
                    (new ShowOrgAgent())->getBreadcrumbs($routeParameters),
                    $headCrumb(
                        $orgSupplierProduct,
                        [
                            'index' => [
                                'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.org.procurement.org_agents.show.supplier_products.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    )
                ),
            'grp.org.procurement.org_suppliers.show.supplier_products.show' =>
                array_merge(
                    (new ShowOrgSupplier())->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'orgSupplier'])),
                    $headCrumb(
                        $orgSupplierProduct,
                        [
                            'index' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show',
                                'parameters' => $routeParameters
                            ],
                            'model' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.show',
                                'parameters' => $routeParameters
                            ]
                        ],
                        $suffix
                    )
                ),
            default => []
        };
    }

    // public function getPrevious(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ?array
    // {
    //     $query = OrgSupplierProduct::select('org_supplier_products.id')->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')->where('code', '<', $orgSupplierProduct->supplierProduct->code);

    //     $query = match ($request->route()->getName()) {
    //         'grp.org.procurement.org_agents.show.org_supplier_products.show' => $query->where('org_supplier_products.org_agent_id', $request->route()->originalParameters()['orgAgent']->id),
    //         'grp.org.procurement.org_agents.show.show.supplier.org_supplier_products.show',
    //         'grp.procurement.supplier.org_supplier_products.show' => $query->where('org_supplier_products.org_supplier_id', $request->route()->originalParameters()['orgSupplier']->id),

    //         default => $query->where('org_supplier_products.organisation_id', $this->organisation->id)
    //     };

    //     $previous = $query->orderBy('code', 'desc')->first();
    //     /** @var OrgSupplierProduct $previous */
    //     $previous = OrgSupplierProduct::find($previous->id);

    //     return $this->getNavigation($previous, $request->route()->getName());
    // }

    // public function getNext(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ?array
    // {
    //     $query = OrgSupplierProduct::select('org_supplier_products.id')->leftJoin('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')->where('code', '>', $orgSupplierProduct->supplierProduct->code);

    //     $query = match ($request->route()->getName()) {
    //         'grp.org.procurement.org_agents.show.org_supplier_products.show' => $query->where('org_supplier_products.org_agent_id', $request->route()->originalParameters()['orgAgent']->id),
    //         'grp.org.procurement.org_agents.show.show.supplier.org_supplier_products.show',
    //         'grp.procurement.supplier.org_supplier_products.show' => $query->where('org_supplier_products.org_supplier_id', $request->route()->originalParameters()['orgSupplier']->id),

    //         default => $query->where('org_supplier_products.organisation_id', $this->organisation->id)
    //     };

    //     $next = $query->orderBy('code')->first();
    //     /** @var OrgSupplierProduct $next */
    //     $next = OrgSupplierProduct::find($next->id);
    //     return $this->getNavigation($next, $request->route()->getName());
    // }

    // private function getNavigation(?OrgSupplierProduct $orgSupplierProduct, string $routeName): ?array
    // {
    //     if (!$orgSupplierProduct) {
    //         return null;
    //     }


    //     return match ($routeName) {
    //         'grp.org.procurement.org_supplier_products.show' => [
    //             'label' => $orgSupplierProduct->supplierProduct->code,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'organisation'       => $this->organisation->slug,
    //                     'orgSupplierProduct' => $orgSupplierProduct->slug
    //                 ]

    //             ]
    //         ],
    //         'grp.org.procurement.org_agents.show.org_supplier_products.show' => [
    //             'label' => $orgSupplierProduct->supplierProduct->code,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'organisation'       => $this->organisation->slug,
    //                     'orgAgent'           => $orgSupplierProduct->orgAgent->slug,
    //                     'orgSupplierProduct' => $orgSupplierProduct->slug
    //                 ]

    //             ]
    //         ]
    //     };
    // }
}
