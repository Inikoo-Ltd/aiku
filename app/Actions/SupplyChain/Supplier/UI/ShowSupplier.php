<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:15:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplier extends GrpAction
{
    use WithSupplierSubNavigation;
    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('supply-chain.edit');
        $this->canDelete = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo("supply-chain.view");
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($supplier->group, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisation($supplier->group, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {

        return Inertia::render(
            'SupplyChain/Supplier',
            [
                'title'       => __('supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next'     => $this->getNext($supplier, $request),
                ],
                'pageHead'    => [
                    'model'   => __('supplier'),
                    'icon'    =>
                        [
                            'icon'  => 'fal fa-person-dolly',
                            'title' => __('Supplier')
                        ],
                    'title'   => $supplier->name,
                    'subNavigation' => $this->getSupplierNavigation($supplier),
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
                                'name'       => 'grp.supply-chain.suppliers.remove',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canEdit && !$supplier->agent_id ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'route' => [
                                'name'       => 'grp.supply-chain.suppliers.show.purchase_orders.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                            'label' => __('purchase order')
                        ] : false,
                    ],
                    'meta'    => [
                        [
                            'name'     => trans_choice('Purchases|Sales', $supplier->stats->number_open_purchase_orders),
                            'number'   => $supplier->stats->number_open_purchase_orders,
                            'route'     => [
                                'grp.supply-chain.supplier_products.show',
                                $supplier->slug
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-person-dolly',
                                'tooltip' => __('sales')
                            ]
                        ],
                        [
                            'name'     => trans_choice('product|products', $supplier->stats->number_supplier_products),
                            'number'   => $supplier->stats->number_supplier_products,
                            'route'     => [
                                'grp.supply-chain.supplier_products.show',
                                $supplier->slug
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-box-usd',
                                'tooltip' => __('products')
                            ]
                        ],
                    ]

                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name' => 'grp.models.supplier.attachment.attach',
                        'parameters' => [
                            'supplier' => $supplier->id,
                        ]
                    ],
                    'detachRoute' => [
                        'name' => 'grp.models.supplier.attachment.detach',
                        'parameters' => [
                            'supplier' => $supplier->id,
                        ],
                        'method'    => 'delete'
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation()
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierShowcase::run($supplier)
                    : Inertia::lazy(fn () => GetSupplierShowcase::run($supplier)),

                SupplierTabsEnum::PURCHASES_SALES->value => $this->tab == SupplierTabsEnum::PURCHASES_SALES->value ?
                    fn () => SupplierProductResource::collection(
                        IndexSupplierProducts::run(
                            parent: $supplier,
                            prefix: 'supplier_products'
                        )
                    )
                    : Inertia::lazy(fn () => SupplierProductResource::collection(IndexSupplierProducts::run($supplier))),


                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplier))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($supplier))),

                SupplierTabsEnum::ATTACHMENTS->value => $this->tab == SupplierTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($supplier))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($supplier)))
            ]
        )->table(IndexSupplierProducts::make()->tableStructure())
            ->table(IndexAttachments::make()->tableStructure(SupplierTabsEnum::ATTACHMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value));
    }


    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters, string $suffix = ''): array
    {

        $headCrumb = function (Supplier $supplier, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Suppliers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $supplier->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.index',
            'grp.supply-chain.suppliers.show' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.suppliers.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.suppliers.show',
                            'parameters' => [$supplier->slug]
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.supply-chain.agents.show.suppliers.show' =>
            array_merge(
                (new ShowAgent())->getBreadcrumbs(
                    $supplier->agent,
                    $routeParameters
                ),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.index',
                            'parameters' => Arr::only($routeParameters, 'agent')

                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }


    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {
        $previous = Supplier::where('code', '<', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.supply-chain.agents.show.suppliers.show') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        $next = Supplier::where('code', '>', $supplier->code)->when(true, function ($query) use ($supplier, $request) {
            if ($request->route()->getName() == 'grp.supply-chain.agents.show.suppliers.show') {
                $query->where('suppliers.agent_id', $supplier->agent_id);
            }
        })->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Supplier $supplier, string $routeName): ?array
    {
        if (!$supplier) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'supplier' => $supplier->slug
                    ]

                ]
            ],
            'grp.supply-chain.agents.show.suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'agent'    => $supplier->agent->slug,
                        'supplier' => $supplier->slug
                    ]

                ]
            ]
        };
    }

}
