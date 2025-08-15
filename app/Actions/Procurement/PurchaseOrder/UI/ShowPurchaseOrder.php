<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\PurchaseOrderTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPurchaseOrder extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());

        return $this->handle($purchaseOrder);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());

        return $this->handle($purchaseOrder);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());

        return $this->handle($purchaseOrder);
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());

        return $this->handle($purchaseOrder);
    }

    public function maya(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->maya   = true;
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());
        return $this->handle($purchaseOrder);
    }

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        // dd($purchaseOrder->id);
        $this->validateAttributes();

        $timeline = [];
        foreach (PurchaseOrderStateEnum::cases() as $state) {
            if ($state === PurchaseOrderStateEnum::IN_PROCESS) {
                $timestamp = $purchaseOrder->created_at;
            } else {
                $timestamp = $purchaseOrder->{$state->snake() . '_at'} ? $purchaseOrder->{$state->snake() . '_at'} : null;
            }

            // If all possible values are null, set the timestamp to null explicitly
            $timestamp = $timestamp ?: null;

            $timeline[$state->value] = [
                'label'     => $state->labels()[$state->value],
                'tooltip'   => $state->labels()[$state->value],
                'key'       => $state->value,
                /* 'icon'    => $palletDelivery->state->stateIcon()[$state->value]['icon'], */
                'timestamp' => $timestamp
            ];
        }

        $finalTimeline = Arr::except(
            $timeline,
            [
                $purchaseOrder->state->value == PurchaseOrderStateEnum::CANCELLED->value
                    ? PurchaseOrderStateEnum::SETTLED->value
                    : PurchaseOrderStateEnum::CANCELLED->value
            ]
        );

        $orderer = [];
        $productListRoute = [];
        if ($purchaseOrder->parent instanceof OrgAgent) {
            $orderer = OrgAgentResource::make($purchaseOrder->parent)->toArray($request);
            $productListRoute = [
                'method'     => 'get',
                'name'       => 'grp.json.org-agent.org-supplier-products',
                'parameters' => [
                    'orgAgent' => $purchaseOrder->parent->slug,
                    'purchaseOrder' => $purchaseOrder->slug
                ]
            ];
        } elseif ($purchaseOrder->parent instanceof OrgSupplier) {
            $orderer = OrgSupplierResource::make($purchaseOrder->parent)->toArray($request);
            $productListRoute = [
                'method'     => 'get',
                'name'       => 'grp.json.org-supplier.org-supplier-products',
                'parameters' => [
                    'orgSupplier' => $purchaseOrder->parent->slug,
                    'purchaseOrder' => $purchaseOrder->slug
                ]
            ];
        }

        $actions = [];
        if ($this->canEdit) {
            $actions = match ($purchaseOrder->state) {
                PurchaseOrderStateEnum::IN_PROCESS => [
                    [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => 'fal fa-plus',
                        'key'     => 'add-products',
                        'label'   => __('add products'),
                        'tooltip' => __('Add products'),
                        'route'   => [
                            'name'       => 'grp.models.purchase-order.transaction.store',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ]
                        ]
                    ],
                    ($purchaseOrder->purchaseOrderTransactions()->count() > 0) ?
                        [
                            'type'    => 'button',
                            'style'   => 'save',
                            'tooltip' => __('submit'),
                            'label'   => __('submit'),
                            'key'     => 'action',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.purchase-order.submit',
                                'parameters' => [
                                    'purchaseOrder' => $purchaseOrder->id
                                ]
                            ]
                        ] : [],
                ],
                PurchaseOrderStateEnum::SUBMITTED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Confirm'),
                        'label'   => __('Confirm'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.confirm',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id
                            ]
                        ]
                    ],
                    [
                        'type'    => 'button',
                        'style'   => 'delete',
                        'tooltip' => __('Cancel'),
                        'label'   => __('Cancel'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.cancel',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id
                            ]
                        ]
                    ],

                ],
                PurchaseOrderStateEnum::CONFIRMED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Settle'),
                        'label'   => __('Settle'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.settle',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id
                            ]
                        ]
                    ],
                    [
                        'type'    => 'button',
                        'style'   => 'delete',
                        'tooltip' => __('Cancel'),
                        'label'   => __('Cancel'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.cancel',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id
                            ]
                        ]
                    ],
                ],
                PurchaseOrderStateEnum::SETTLED => [
                    [
                        'type'    => 'button',
                        'style'   => 'delete',
                        'tooltip' => __('Not Received'),
                        'label'   => __('Not Received'),
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.not-received',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id
                            ]
                        ]
                    ]
                ],
                default => []
            };
        }
        // dd($orderer);
        return Inertia::render(
            'Procurement/PurchaseOrder',
            [
                'title'       => __('purchase order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $purchaseOrder,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($purchaseOrder, $request),
                    'next'     => $this->getNext($purchaseOrder, $request),
                ],
                'pageHead'    => [
                    'icon'  =>
                    [
                        'icon'  => ['fal', 'clipboard-list'],
                        'title' => __('purchase order')
                    ],
                    'title' => $purchaseOrder->reference,
                    'afterTitle'    => [
                        'label' => __($purchaseOrder->parent_type)
                    ],
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions' => $actions

                ],
                'routes'      => [
                    'updatePurchaseOrderRoute' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ]
                    ],
                    'products_list'    => $productListRoute,
                ],
                // 'alert'   => [  // TODO
                //     'status'        => 'danger',
                //     'title'         => 'Dummy Alert from BE',
                //     'description'   => 'Dummy description'
                // ],
                'timelines'   => $finalTimeline,

                'box_stats'      => [
                    'orderer'      => [
                        'type' => $purchaseOrder->parent_type,
                        'data' => $orderer
                    ],
                    'mid_block'      => [
                        'gross_weight' => $purchaseOrder->gross_weight,
                        'net_weight'   => $purchaseOrder->net_weight,
                        'notes'          => $purchaseOrder->notes,
                        'delivery_state' => $purchaseOrder->delivery_state,
                    ],

                    'order_summary' => [
                        [
                            [
                                'label'       => 'Transactions',
                                'quantity'    => $purchaseOrder->purchaseOrderTransactions()->count(),
                                'price_base'  => 'Multiple',
                                'price_total' => $purchaseOrder->cost_items
                            ],
                        ],
                        [
                            [
                                'label'       => 'Extra',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_extra
                            ],
                            [
                                'label'       => 'Shipping',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_shipping
                            ]
                        ],
                        [
                            [
                                'label'       => 'Duties',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_duties
                            ],
                            [
                                'label'       => 'Tax',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_tax
                            ]
                        ],
                        [
                            [
                                'label'       => 'Total',
                                'price_total' => $purchaseOrder->cost_total
                            ]
                        ],
                        'currency' => CurrencyResource::make($purchaseOrder->currency),
                    ],
                ],
                'currency'       => CurrencyResource::make($purchaseOrder->currency)->toArray(request()),
                'data'           => PurchaseOrderResource::make($purchaseOrder),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PurchaseOrderTabsEnum::navigation()
                ],

                // PurchaseOrderTabsEnum::SHOWCASE->value => $this->tab == PurchaseOrderTabsEnum::SHOWCASE->value ?
                //     fn () => new PurchaseOrderResource(($purchaseOrder))
                //     : Inertia::lazy(fn () => new PurchaseOrderResource(($purchaseOrder))),

                PurchaseOrderTabsEnum::TRANSACTIONS->value => $this->tab == PurchaseOrderTabsEnum::TRANSACTIONS->value ?
                    fn() => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder))
                    : Inertia::lazy(fn() => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder))),

                PurchaseOrderTabsEnum::PRODUCTS->value => $this->tab == PurchaseOrderTabsEnum::PRODUCTS->value ?
                    fn() => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder))
                    : Inertia::lazy(fn() => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder))),

                PurchaseOrderTabsEnum::HISTORY->value => $this->tab == PurchaseOrderTabsEnum::HISTORY->value ?
                    fn() => HistoryResource::collection(IndexHistory::run($purchaseOrder))
                    : Inertia::lazy(fn() => HistoryResource::collection(IndexHistory::run($purchaseOrder))),

                PurchaseOrderTabsEnum::ATTACHMENTS->value => $this->tab == PurchaseOrderTabsEnum::ATTACHMENTS->value ?
                    fn() => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder))
                    : Inertia::lazy(fn() => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder)))
            ]
        )->table(IndexPurchaseOrderTransactions::make()->tableStructure(prefix: PurchaseOrderTabsEnum::TRANSACTIONS->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: PurchaseOrderTabsEnum::ATTACHMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: PurchaseOrderTabsEnum::HISTORY->value));
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }

    public function getBreadcrumbs(PurchaseOrder $purchaseOrder, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (PurchaseOrder $purchaseOrder, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Purchase Orders')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $purchaseOrder->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.procurement.purchase_orders.show',
            => array_merge(
                (new ShowProcurementDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.purchase_orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.purchase_orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_agents.show.purchase-orders.show'
            => array_merge(
                (new ShowOrgAgent())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_suppliers.show.purchase-orders.show'
            => array_merge(
                (new ShowOrgSupplier())->getBreadcrumbs('grp.org.procurement.org_suppliers.show', $routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show.purchase-orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_partners.show.purchase-orders.show'
            => array_merge(
                (new ShowOrgPartner())->getBreadcrumbs($purchaseOrder->parent, $routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_partners.show.purchase-orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_partners.show.purchase-orders.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }


    public function getPrevious(PurchaseOrder $purchaseOrder, ActionRequest $request): ?array
    {
        $previous = PurchaseOrder::where('reference', '<', $purchaseOrder->reference)->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PurchaseOrder $purchaseOrder, ActionRequest $request): ?array
    {
        $next = PurchaseOrder::where('reference', '>', $purchaseOrder->reference)->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PurchaseOrder $purchaseOrder, string $routeName): ?array
    {
        if (!$purchaseOrder) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.purchase_orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'purchaseOrder' => $purchaseOrder->slug
                    ]

                ]
            ],
            'grp.org.procurement.org_agents.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgAgent'     => $purchaseOrder->parent->slug,
                        'purchaseOrder' => $purchaseOrder->slug
                    ]

                ]
            ],
            'grp.org.procurement.org_suppliers.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgSupplier'   => $purchaseOrder->parent->slug,
                        'purchaseOrder' => $purchaseOrder->slug
                    ]

                ]
            ],
            'grp.org.procurement.org_partners.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgPartner'    => $purchaseOrder->parent->id,
                        'purchaseOrder' => $purchaseOrder->slug
                    ]

                ]
            ],
        };
    }
}
