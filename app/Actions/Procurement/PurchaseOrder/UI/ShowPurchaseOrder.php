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

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values());

        return $this->handle($purchaseOrder);
    }

    public function maya(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->maya = true;
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

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->validateAttributes();

        $orderer = [];
        $productListRoute = [];
        $actions = [];

        if ($purchaseOrder->parent instanceof OrgAgent) {
            $orderer = OrgAgentResource::make($purchaseOrder->parent)->toArray($request);
            $productListRoute = [
                'method'     => 'get',
                'name'       => 'grp.json.org-agent.org-supplier-products',
                'parameters' => [
                    'orgAgent' => $purchaseOrder->parent->slug,
                    'purchaseOrder' => $purchaseOrder->slug,
                ],
            ];
        } elseif ($purchaseOrder->parent instanceof OrgSupplier) {
            $orderer = OrgSupplierResource::make($purchaseOrder->parent)->toArray($request);
            $productListRoute = [
                'method'     => 'get',
                'name'       => 'grp.json.org-supplier.org-supplier-products',
                'parameters' => [
                    'orgSupplier' => $purchaseOrder->parent->slug,
                    'purchaseOrder' => $purchaseOrder->slug,
                ],
            ];
        }

        if ($this->canEdit) {
            $actions = match ($purchaseOrder->state) {
                PurchaseOrderStateEnum::IN_PROCESS => [
                    [
                        'label'   => __('Add Products'),
                        'tooltip' => __('Add Products'),
                        'icon'    => 'fal fa-plus',
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'key'     => 'add-products',
                        'route'   => [
                            'name'       => 'grp.models.purchase-order.transaction.store',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                    ($purchaseOrder->purchaseOrderTransactions()->count() > 0) ?
                    [
                        'label'   => __('Submit'),
                        'tooltip' => __('Submit'),
                        'type'    => 'button',
                        'style'   => 'save',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.submit',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ] : [],
                ],
                PurchaseOrderStateEnum::SUBMITTED => [
                    [
                        'label'   => __('Confirm'),
                        'tooltip' => __('Confirm'),
                        'type'    => 'button',
                        'style'   => 'save',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.confirm',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                    [
                        'label'   => __('Cancel'),
                        'tooltip' => __('Cancel'),
                        'type'    => 'button',
                        'style'   => 'delete',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.cancel',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                ],
                PurchaseOrderStateEnum::CONFIRMED => [
                    [
                        'label'   => __('Settle'),
                        'tooltip' => __('Settle'),
                        'type'    => 'button',
                        'style'   => 'save',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.settle',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                    [
                        'label'   => __('Cancel'),
                        'tooltip' => __('Cancel'),
                        'type'    => 'button',
                        'style'   => 'delete',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.cancel',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                ],
                PurchaseOrderStateEnum::SETTLED => [
                    [
                        'label'   => __('Not Received'),
                        'tooltip' => __('Not Received'),
                        'type'    => 'button',
                        'style'   => 'delete',
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.purchase-order.not-received',
                            'parameters' => [
                                'purchaseOrder' => $purchaseOrder->id,
                            ],
                        ],
                    ],
                ],
                default => []
            };
        }

        return Inertia::render(
            'Procurement/PurchaseOrder',
            [
                'title'       => __('Purchase Order'),
                'breadcrumbs' => $this->getBreadcrumbs($purchaseOrder, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($purchaseOrder, $request),
                    'next'     => $this->getNext($purchaseOrder, $request),
                ],
                'pageHead'    => [
                    'title' => __('Purchase Order'),
                    'icon'  => [
                        'icon'  => ['fal', 'clipboard-list'],
                        'title' => __('Purchase Order'),
                    ],
                    'afterTitle' => [
                        'label' => $purchaseOrder->reference,
                    ],
                    'edit' => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                    ] : false,
                    'actions' => $actions,
                ],
                'routes'      => [
                    'updatePurchaseOrderRoute' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                    'products_list' => $productListRoute,
                ],
                'box_stats'   => [
                    'orderer'       => [
                        'type' => $purchaseOrder->parent_type,
                        'data' => $orderer,
                    ],
                    'mid_block'     => [
                        'gross_weight'   => $purchaseOrder->gross_weight,
                        'net_weight'     => $purchaseOrder->net_weight,
                        'notes'          => $purchaseOrder->notes,
                        'delivery_state' => $purchaseOrder->delivery_state,
                    ],
                    'order_summary' => [
                        [
                            [
                                'label'       => 'Transactions',
                                'quantity'    => $purchaseOrder->purchaseOrderTransactions()->count(),
                                'price_base'  => 'Multiple',
                                'price_total' => $purchaseOrder->cost_items,
                            ],
                        ],
                        [
                            [
                                'label'       => 'Extra',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_extra,
                            ],
                            [
                                'label'       => 'Shipping',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_shipping,
                            ],
                        ],
                        [
                            [
                                'label'       => 'Duties',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_duties,
                            ],
                            [
                                'label'       => 'Tax',
                                'information' => '',
                                'price_total' => $purchaseOrder->cost_tax,
                            ],
                        ],
                        [
                            [
                                'label'       => 'Total',
                                'price_total' => $purchaseOrder->cost_total,
                            ],
                        ],
                        'currency' => CurrencyResource::make($purchaseOrder->currency),
                    ],
                ],
                'timelines'   => $this->getTimeline($purchaseOrder),
                'currency'    => CurrencyResource::make($purchaseOrder->currency)->toArray(request()),
                'data'        => PurchaseOrderResource::make($purchaseOrder),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PurchaseOrderTabsEnum::navigation(),
                ],

                // PurchaseOrderTabsEnum::SHOWCASE->value => $this->tab == PurchaseOrderTabsEnum::SHOWCASE->value ?
                //     fn () => new PurchaseOrderResource(($purchaseOrder))
                //     : Inertia::optional(fn () => new PurchaseOrderResource(($purchaseOrder))),

                PurchaseOrderTabsEnum::TRANSACTIONS->value => $this->tab == PurchaseOrderTabsEnum::TRANSACTIONS->value ?
                    fn () => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder))
                    : Inertia::optional(fn () => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder))),

                PurchaseOrderTabsEnum::PRODUCTS->value => $this->tab == PurchaseOrderTabsEnum::PRODUCTS->value ?
                    fn () => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder))
                    : Inertia::optional(fn () => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder))),

                PurchaseOrderTabsEnum::HISTORY->value => $this->tab == PurchaseOrderTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder))),

                PurchaseOrderTabsEnum::ATTACHMENTS->value => $this->tab == PurchaseOrderTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder)))
            ]
        )->table(IndexPurchaseOrderTransactions::make()->tableStructure(prefix: PurchaseOrderTabsEnum::TRANSACTIONS->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: PurchaseOrderTabsEnum::ATTACHMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: PurchaseOrderTabsEnum::HISTORY->value));
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }

    public function getTimeline(PurchaseOrder $purchaseOrder): array
    {
        $state  = $purchaseOrder->state;
        $labels = PurchaseOrderStateEnum::labels();

        $timeline = [
            PurchaseOrderStateEnum::IN_PROCESS->value => [
                'label'     => $labels[PurchaseOrderStateEnum::IN_PROCESS->value],
                'tooltip'   => $labels[PurchaseOrderStateEnum::IN_PROCESS->value],
                'key'       => PurchaseOrderStateEnum::IN_PROCESS->value,
                'timestamp' => $purchaseOrder->created_at,
            ],
            PurchaseOrderStateEnum::SUBMITTED->value => [
                'label'     => $labels[PurchaseOrderStateEnum::SUBMITTED->value],
                'tooltip'   => $labels[PurchaseOrderStateEnum::SUBMITTED->value],
                'key'       => PurchaseOrderStateEnum::SUBMITTED->value,
                'timestamp' => $purchaseOrder->submitted_at,
            ],
            PurchaseOrderStateEnum::CONFIRMED->value => [
                'label'     => $labels[PurchaseOrderStateEnum::CONFIRMED->value],
                'tooltip'   => $labels[PurchaseOrderStateEnum::CONFIRMED->value],
                'key'       => PurchaseOrderStateEnum::CONFIRMED->value,
                'timestamp' => $purchaseOrder->confirmed_at,
            ],
        ];

        foreach ([PurchaseOrderStateEnum::SETTLED, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED] as $terminalState) {
            if ($state === $terminalState) {
                $timeline[$terminalState->value] = [
                    'label'     => $labels[$terminalState->value],
                    'tooltip'   => $labels[$terminalState->value],
                    'key'       => $terminalState->value,
                    'timestamp' => $purchaseOrder->{$terminalState->snake() . '_at'} ?: null,
                ];

                return $timeline;
            }
        }

        if (in_array($state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::SUBMITTED], true)) {
            // TODO: No source for the estimated dispatch date in aiku (not imported from Aurora, no column),
            // so it always shows "No estimated production date" for now.
            $timeline['estimated_dispatch'] = [
                'label'     => __('Estimated dispatch'),
                'tooltip'   => __('Estimated dispatch'),
                'key'       => 'estimated_dispatch',
                'icon'      => 'fal fa-truck',
                'sub_label' => __('No estimated production date'),
                'timestamp' => null,
            ];
        }

        // TODO: No source for the estimated delivery in aiku (not imported from Aurora), so it's a
        // placeholder for now. Likely estimated_delivery = confirmed_at + lead days (no lead days yet,
        // hence "no estimated"), and once dispatched it becomes the stock delivery estimated received.
        $timeline['estimated_delivery'] = [
            'label'     => __('Estimated delivery'),
            'tooltip'   => __('Estimated delivery'),
            'key'       => 'estimated_delivery',
            'sub_label' => __('No estimated delivery date'),
            'timestamp' => null,
        ];

        return $timeline;
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

    public function getNavigation(?PurchaseOrder $purchaseOrder, string $routeName): ?array
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
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
            'grp.org.procurement.org_agents.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgAgent'      => $purchaseOrder->parent->slug,
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
            'grp.org.procurement.org_suppliers.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgSupplier'   => $purchaseOrder->parent->slug,
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
            'grp.org.procurement.org_partners.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $purchaseOrder->organisation->slug,
                        'orgPartner'    => $purchaseOrder->parent->id,
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
        };
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
                            'label' => __('Purchase Orders'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $purchaseOrder->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.procurement.purchase_orders.show' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.purchase_orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder']),
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.purchase_orders.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_agents.show.purchase-orders.show' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder']),
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_suppliers.show.purchase-orders.show' => array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs('grp.org.procurement.org_suppliers.show', $routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder']),
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show.purchase-orders.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix
                )
            ),
            'grp.org.procurement.org_partners.show.purchase-orders.show' => array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($purchaseOrder->parent, $routeParameters),
                $headCrumb(
                    $purchaseOrder,
                    [
                        'index' => [
                            'name'       => 'grp.org.procurement.org_partners.show.purchase-orders.index',
                            'parameters' => Arr::except($routeParameters, ['purchaseOrder']),
                        ],
                        'model' => [
                            'name'       => 'grp.org.procurement.org_partners.show.purchase-orders.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
