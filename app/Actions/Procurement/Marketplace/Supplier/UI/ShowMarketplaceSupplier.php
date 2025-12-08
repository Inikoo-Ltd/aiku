<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:15:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\Marketplace\Supplier\UI;

use App\Actions\InertiaAction;
use App\Actions\Procurement\Marketplace\SupplierProduct\UI\IndexMarketplaceSupplierProducts;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\MarketplaceSupplierTabsEnum;
use App\Http\Resources\Procurement\MarketplaceSupplierProductResource;
use App\Http\Resources\Procurement\MarketplaceSupplierResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMarketplaceSupplier extends InertiaAction
{
    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('procurement.edit');
        $this->canDelete = $request->user()->authTo('procurement.edit');

        return $request->user()->authTo('procurement.view');
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMarketplaceAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($request)->withTab(MarketplaceSupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {

        return Inertia::render(
            'Procurement/MarketplaceSupplier',
            [
                'title' => __('supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation' => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next' => $this->getNext($supplier, $request),
                ],
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'person-dolly'],
                        'title' => __('Supplier'),
                    ],
                    'title' => $supplier->name,
                    'actions' => [
                        $this->canEdit ? [
                            'type' => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name' => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ] : false,
                        $this->canDelete ? [
                            'type' => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name' => 'grp.org.procurement.marketplace.org_suppliers.remove',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ] : false,
                    ],
                    'meta' => [
                        [
                            'route' => match (true) {
                                $supplier->agent_id > 0 => ['grp.org.procurement.marketplace.org_agents.show.org_suppliers.show.org_supplier_products.index', [$supplier->agent->slug, $supplier->slug]],
                                default => ['grp.org.procurement.marketplace.org_suppliers.show.org_supplier_products.index', $supplier->slug],
                            },
                            'name' => trans_choice('product|products', $supplier->stats->number_supplier_products),
                            'number' => $supplier->stats->number_supplier_products,
                            'leftIcon' => [
                                'icon' => 'fal fa-box-usd',
                                'tooltip' => __('products'),
                            ],
                        ],
                    ],

                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => MarketplaceSupplierTabsEnum::navigation(),
                ],

                MarketplaceSupplierTabsEnum::SHOWCASE->value => $this->tab == MarketplaceSupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetMarketplaceSupplierShowcase::run($supplier)
                    : Inertia::lazy(fn () => GetMarketplaceSupplierShowcase::run($supplier)),

                MarketplaceSupplierTabsEnum::SUPPLIER_PRODUCTS->value => $this->tab == MarketplaceSupplierTabsEnum::SUPPLIER_PRODUCTS->value ?
                    fn () => MarketplaceSupplierProductResource::collection(IndexMarketplaceSupplierProducts::run($supplier))
                    : Inertia::lazy(fn () => MarketplaceSupplierProductResource::collection(IndexMarketplaceSupplierProducts::run($supplier))),

                MarketplaceSupplierTabsEnum::SHOWCASE->value => $this->tab == MarketplaceSupplierTabsEnum::SHOWCASE->value ?
                    fn () => MarketplaceSupplierResource::make($supplier)->getArray()
                    : Inertia::lazy(fn () => MarketplaceSupplierResource::make($supplier)->getArray()),

            ]
        )->table(IndexMarketplaceSupplierProducts::make()->tableStructure());
    }

    public function jsonResponse(Supplier $supplier): MarketplaceSupplierResource
    {
        return new MarketplaceSupplierResource($supplier);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Supplier $supplier, array $routeParameters, string $suffix) {
            return [
                [

                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __("supplier's marketplace"),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $supplier->code,
                        ],

                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.procurement.marketplace.org_suppliers.show' => array_merge(
                (new ShowProcurementDashboard)->getBreadcrumbs(),
                $headCrumb(
                    $routeParameters['supplier'],
                    [
                        'index' => [
                            'name' => 'grp.org.procurement.marketplace.org_suppliers.index',
                            'parameters' => [],
                        ],
                        'model' => [
                            'name' => 'grp.org.procurement.marketplace.org_suppliers.show',
                            'parameters' => [$routeParameters['supplier']->slug],
                        ],
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show' => array_merge(
                (new \App\Actions\SupplyChain\Agent\UI\ShowAgent)->getBreadcrumbs(
                    ['agent' => $routeParameters['agent']]
                ),
                $headCrumb(
                    $routeParameters['supplier'],
                    [
                        'index' => [
                            'name' => 'grp.org.procurement.marketplace.org_agents.show.org_suppliers.index',
                            'parameters' => [
                                $routeParameters['agent']->slug,
                            ],
                        ],
                        'model' => [
                            'name' => 'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show',
                            'parameters' => [
                                $routeParameters['agent']->slug,
                                $routeParameters['supplier']->slug,
                            ],
                        ],
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {

        $previous = Supplier::where('code', '<', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        $next = Supplier::where('code', '>', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Supplier $supplier, string $routeName): ?array
    {
        if (! $supplier) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.marketplace.org_suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'supplier' => $supplier->slug,
                    ],

                ],
            ],
            'grp.org.procurement.marketplace.org_agents.show.org_suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'agent' => $supplier->agent->slug,
                        'supplier' => $supplier->slug,
                    ],

                ],
            ]
        };
    }
}
