<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\ResolvePurchaseOrderDeliveryAddress;
use App\Actions\Procurement\PurchaseOrder\Traits\WithPurchaseOrderWeightAndVolume;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\UI\Procurement\PurchaseOrderTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPurchaseOrderWeightAndVolume;
    use WithAgentOrganisation;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values(), $this->defaultTab($purchaseOrder));
        $this->authorizeProcurementRecord($purchaseOrder);

        return $this->handle($purchaseOrder);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values(), $this->defaultTab($purchaseOrder));
        $this->authorizeProcurementRecord($purchaseOrder);

        return $this->handle($purchaseOrder);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values(), $this->defaultTab($purchaseOrder));
        $this->authorizeProcurementRecord($purchaseOrder);

        return $this->handle($purchaseOrder);
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values(), $this->defaultTab($purchaseOrder));
        $this->authorizeProcurementRecord($purchaseOrder);

        return $this->handle($purchaseOrder);
    }

    private function defaultTab(PurchaseOrder $purchaseOrder): string
    {
        $isEmptyOpenOrder = $purchaseOrder->state == PurchaseOrderStateEnum::IN_PROCESS
            && ($purchaseOrder->parent instanceof OrgAgent || $purchaseOrder->parent instanceof OrgSupplier)
            && !$purchaseOrder->purchaseOrderTransactions()->exists();

        return $isEmptyOpenOrder ? PurchaseOrderTabsEnum::PRODUCTS->value : PurchaseOrderTabsEnum::ITEMS->value;
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->validateAttributes();

        $showProductsTab = $purchaseOrder->state == PurchaseOrderStateEnum::IN_PROCESS
            && ($purchaseOrder->parent instanceof OrgAgent || $purchaseOrder->parent instanceof OrgSupplier);

        $orderer = [];
        $productListRoute = [];
        $weightAndVolume = $this->getPurchaseOrderWeightAndVolume($purchaseOrder);
        $deliveryStats = $this->getDeliveryStats($purchaseOrder);
        $hasItems = $purchaseOrder->number_purchase_order_transactions > 0;
        $deliveryAddress = ResolvePurchaseOrderDeliveryAddress::run(
            $purchaseOrder->organisation,
            Arr::get($purchaseOrder->data, 'delivery_address')
        );

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
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.edit',
                                'parameters' => [$purchaseOrder->organisation->slug, $purchaseOrder->slug],
                            ],
                        ] : false,
                        [
                            'type'   => 'button',
                            'style'  => 'tertiary',
                            'label'  => 'PDF',
                            'target' => '_blank',
                            'icon'   => 'fal fa-file-pdf',
                            'key'    => 'pdf',
                            'route'  => [
                                'name'       => 'grp.org.procurement.purchase_orders.pdf',
                                'parameters' => [$purchaseOrder->organisation->slug, $purchaseOrder->slug],
                            ],
                        ],
                        ...($this->emailToSupplierAction($purchaseOrder) ?? []),
                        ...($this->canEdit ? $this->getActions($purchaseOrder, $showProductsTab) : []),
                    ],
                ],
                'data'                     => PurchaseOrderResource::make($purchaseOrder),
                'timelines'                => $this->getTimeline($purchaseOrder),
                'stock_delivery_timelines' => $this->getStockDeliveryTimelines($purchaseOrder),
                'delivery_items'            => $purchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED
                    ? $purchaseOrder->purchaseOrderTransactions()
                        ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
                        ->with('supplierProduct:id,code,name')
                        ->get(['id', 'supplier_product_id', 'quantity_ordered'])
                        ->map(fn (PurchaseOrderTransaction $transaction) => [
                            'id'               => $transaction->id,
                            'code'             => $transaction->supplierProduct?->code,
                            'name'             => $transaction->supplierProduct?->name,
                            'quantity_ordered' => $transaction->quantity_ordered,
                        ])->values()
                    : [],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $showProductsTab
                        ? PurchaseOrderTabsEnum::navigation()
                        : PurchaseOrderTabsEnum::navigationExcept([PurchaseOrderTabsEnum::PRODUCTS]),
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
                    'first_block'   => [
                        'orderer'  => $orderer,
                        'delivery' => [
                            'type'             => Arr::get($purchaseOrder->data, 'delivery_type'),
                            'incoterm'         => Arr::get($purchaseOrder->data, 'incoterm'),
                            'port_of_export'   => Arr::get($purchaseOrder->data, 'port_of_export'),
                            'port_of_import'   => Arr::get($purchaseOrder->data, 'port_of_import'),
                            'delivery_address' => $deliveryAddress,
                        ],
                    ],
                    'second_block'     => [
                        'state'                    => $purchaseOrder->state->labels()[$purchaseOrder->state->value],
                        'delivery_state'           => PurchaseOrderDeliveryStateEnum::stateIcon()[$purchaseOrder->delivery_state->value],
                        'total_items'              => $purchaseOrder->number_purchase_order_transactions,
                        'total_delivery_items'     => $deliveryStats['total_delivery_items'],
                        'total_placed_items'       => $deliveryStats['total_placed_items'],
                        'is_delivery_items_active' => $deliveryStats['is_delivery_items_active'],
                        'is_placed_items_active'   => $deliveryStats['is_placed_items_active'],
                        'weight'                   => $hasItems ? Arr::get($weightAndVolume, 'gross_weight') : 0,
                        'volume'                   => $hasItems ? Arr::get($weightAndVolume, 'volume') : 0,
                        'is_weight_partial'        => $hasItems && Arr::get($weightAndVolume, 'is_weight_partial'),
                        'is_volume_partial'        => $hasItems && Arr::get($weightAndVolume, 'is_volume_partial'),
                        'production_time'          => null, // Todo: not sure in which states this should appear, so far only known when the purchase order is cancelled
                        'delivery_time'            => null, // Todo: not sure in which states this should appear, so far only known when the purchase order is cancelled
                    ],
                    'third_block' => [
                        'currency'     => $purchaseOrder->currency?->code,
                        'org_currency' => $purchaseOrder->organisation?->currency?->code,
                        'org_exchange' => $purchaseOrder->org_exchange,
                        'items'        => $purchaseOrder->cost_items,
                        'extra'        => $purchaseOrder->cost_extra,
                        'shipping'     => $purchaseOrder->cost_shipping,
                        'duties'       => $purchaseOrder->cost_duties,
                        'tax'          => $purchaseOrder->cost_tax,
                        'total'        => $purchaseOrder->cost_total,
                        'org_items'    => $purchaseOrder->purchaseOrderTransactions()->sum('org_net_amount'),
                    ],
                ],

                PurchaseOrderTabsEnum::ITEMS->value => $this->tab == PurchaseOrderTabsEnum::ITEMS->value ?
                    fn () => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder, PurchaseOrderTabsEnum::ITEMS->value))
                    : Inertia::optional(fn () => PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder, PurchaseOrderTabsEnum::ITEMS->value))),

                PurchaseOrderTabsEnum::PRODUCTS->value => $showProductsTab && $this->tab == PurchaseOrderTabsEnum::PRODUCTS->value ?
                    fn () => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder, PurchaseOrderTabsEnum::PRODUCTS->value))
                    : Inertia::optional(fn () => $showProductsTab ? PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder, PurchaseOrderTabsEnum::PRODUCTS->value)) : null),

                PurchaseOrderTabsEnum::SHOWCASE->value => $this->tab == PurchaseOrderTabsEnum::SHOWCASE->value ?
                    fn () => GetPurchaseOrderData::run($purchaseOrder)
                    : Inertia::optional(fn () => GetPurchaseOrderData::run($purchaseOrder)),

                PurchaseOrderTabsEnum::NOTES->value => $this->tab == PurchaseOrderTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($purchaseOrder, PurchaseOrderTabsEnum::NOTES->value))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($purchaseOrder, PurchaseOrderTabsEnum::NOTES->value))),

                'note_store_route' => [
                    'name'       => 'grp.models.purchase-order.note.store',
                    'parameters' => [$purchaseOrder->id],
                ],

                PurchaseOrderTabsEnum::HISTORY->value => $this->tab == PurchaseOrderTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder, PurchaseOrderTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder, PurchaseOrderTabsEnum::HISTORY->value))),
            ]
        )->table(IndexPurchaseOrderTransactions::make()->tableStructure($purchaseOrder, prefix: PurchaseOrderTabsEnum::ITEMS->value))
            ->table(IndexPurchaseOrderOrgSupplierProducts::make()->tableStructure(prefix: PurchaseOrderTabsEnum::PRODUCTS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: PurchaseOrderTabsEnum::NOTES->value))
            ->table(IndexHistory::make()->tableStructure(prefix: PurchaseOrderTabsEnum::HISTORY->value));
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }

    private function emailToSupplierAction(PurchaseOrder $purchaseOrder): ?array
    {
        $supplier = $purchaseOrder->parent instanceof OrgSupplier ? $purchaseOrder->parent->supplier : null;

        if (!$supplier || !Arr::get($supplier->settings, 'po_by_email')) {
            return null;
        }

        $email   = Arr::get($supplier->settings, 'po_email') ?: $supplier->email;
        $subject = __('Purchase order :reference from :organisation', ['reference' => $purchaseOrder->reference, 'organisation' => $purchaseOrder->organisation->name]);
        $body    = __("Hello,\n\nPlease find attached our purchase order :reference.\n\nKind regards,\n:organisation", ['reference' => $purchaseOrder->reference, 'organisation' => $purchaseOrder->organisation->name]);

        return [
            [
                'type'    => 'button',
                'style'   => 'secondary',
                'label'   => __('Email to supplier'),
                'tooltip' => $email ? __('Opens your email with :email and downloads the PDF to attach', ['email' => $email]) : __('This supplier has no email address'),
                'icon'    => 'fal fa-envelope',
                'key'     => 'email_to_supplier',
                'mailto'  => $email ? 'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($body) : null,
                'pdfUrl'  => route('grp.org.procurement.purchase_orders.pdf', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]),
            ]
        ];
    }

    public function getActions(PurchaseOrder $purchaseOrder, bool $showProductsTab): array
    {
        return match ($purchaseOrder->state) {
            PurchaseOrderStateEnum::IN_PROCESS => [
                $showProductsTab ? [
                    'label'   => __('Add Product'),
                    'tooltip' => __('Add Product'),
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'icon'    => 'fal fa-plus',
                    'key'     => 'add_product',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.purchase-order.transaction.store',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ] : [],
                $purchaseOrder->purchaseOrderTransactions()
                    ->where('state', PurchaseOrderTransactionStateEnum::IN_PROCESS)
                    ->exists() ?
                [
                    'label'   => __('Submit'),
                    'tooltip' => __('Submit Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-paper-plane',
                    'key'     => 'submit_purchase_order',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.submit',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ] : [],
                [
                    'label'   => __('Delete'),
                    'tooltip' => __('Delete Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'icon'    => 'fal fa-trash-alt',
                    'key'     => 'delete_purchase_order',
                    'route'   => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.purchase-order.delete',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ],
            ],
            PurchaseOrderStateEnum::SUBMITTED => [
                [
                    'label'   => __('Confirm'),
                    'tooltip' => __('Set as confirmed by the supplier.'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'key'     => 'confirm_purchase_order',
                    'estimated_receiving_date' => Arr::get($purchaseOrder->data, 'estimated_receiving_date'),
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.confirm',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ],
                [
                    'label'   => __('Undo Submit'),
                    'tooltip' => __('Revert Purchase Order to In Process'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'icon'    => 'fal fa-paper-plane',
                    'key'     => 'undo_submit_purchase_order',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.undo-submit',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ],
                [
                    'label'   => __('Cancel'),
                    'tooltip' => __('Cancel Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'key'     => 'cancel_purchase_order',
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
                    'label'   => __('Delivery date'),
                    'tooltip' => __('Change estimated delivery date'),
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'icon'    => 'fal fa-calendar-alt',
                    'key'     => 'edit_estimated_delivery_date',
                    'estimated_receiving_date' => Arr::get($purchaseOrder->data, 'estimated_receiving_date'),
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                ],
                $this->hasActiveStockDelivery($purchaseOrder) ? [] : [
                    'label'   => __('New Delivery'),
                    'tooltip' => __('Create Stock Delivery from this Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'create',
                    'icon'    => 'fal fa-plus',
                    'key'     => 'new_stock_delivery',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.purchase-order.stock-delivery.store',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                ],
                $this->hasActiveStockDelivery($purchaseOrder) ? [] : [
                    'label'   => __('Undo Confirm'),
                    'tooltip' => __('Revert Purchase Order to Submitted'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'icon'    => 'fal fa-check-double',
                    'key'     => 'undo_confirm_purchase_order',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.undo-confirm',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                ],
            ],
            default => []
        };
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
                if ($purchaseOrder->confirmed_at === null) {
                    unset($timeline[PurchaseOrderStateEnum::CONFIRMED->value]);
                }

                $timeline[$terminalState->value] = [
                    'label'     => $labels[$terminalState->value],
                    'tooltip'   => $labels[$terminalState->value],
                    'key'       => $terminalState->value,
                    'timestamp' => $purchaseOrder->{$terminalState->snake() . '_at'} ?: null,
                ];

                return $timeline;
            }
        }

        $deliveryProgression = [
            PurchaseOrderDeliveryStateEnum::IN_PROCESS->value,
            PurchaseOrderDeliveryStateEnum::CONFIRMED->value,
            PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
            PurchaseOrderDeliveryStateEnum::DISPATCHED->value,
            PurchaseOrderDeliveryStateEnum::RECEIVED->value,
            PurchaseOrderDeliveryStateEnum::CHECKED->value,
            PurchaseOrderDeliveryStateEnum::PLACED->value,
        ];

        $deliveryRank   = array_search($purchaseOrder->delivery_state->value, $deliveryProgression, true);
        $dispatchedRank = array_search(PurchaseOrderDeliveryStateEnum::DISPATCHED->value, $deliveryProgression, true);
        $receivedRank   = array_search(PurchaseOrderDeliveryStateEnum::RECEIVED->value, $deliveryProgression, true);

        $hasDispatched = $deliveryRank !== false && $deliveryRank >= $dispatchedRank;
        $hasReceived   = $deliveryRank !== false && $deliveryRank >= $receivedRank;

        if (!$hasDispatched) {
            // TODO: Default should come from the Supplier/Agent "Production waiting time (days)"
            // (no such field yet). While the purchase order is not confirmed, only a sub label should
            // show (e.g. "Estimated X days after confirmation"); once confirmed, the default timestamp
            // is calculated as confirmed_at + production waiting days.
            $estimatedProductionDate = Arr::get($purchaseOrder->data, 'estimated_production_date');

            $timeline['estimated_dispatch'] = [
                'label'     => __('Estimated dispatch'),
                'tooltip'   => __('Estimated dispatch'),
                'key'       => 'estimated_dispatch',
                'icon'      => 'fal fa-truck',
                'sub_label' => $estimatedProductionDate ? null : __('No estimated production date'),
                'timestamp' => $estimatedProductionDate,
            ];
        }

        if (!$hasReceived) {
            // TODO: Default should come from the Supplier/Agent "Delivery time (days)" (no such field yet).
            // While the purchase order is not confirmed, only a sub label should show
            // (e.g. "Estimated 30 days after confirmation"); once confirmed, the default timestamp is
            // calculated as estimated dispatch + delivery days, and once dispatched it becomes the
            // stock delivery estimated received date.
            $estimatedReceivingDate = Arr::get($purchaseOrder->data, 'estimated_receiving_date');

            $timeline['estimated_delivery'] = [
                'label'     => __('Estimated delivery'),
                'tooltip'   => __('Estimated delivery'),
                'key'       => 'estimated_delivery',
                'sub_label' => $estimatedReceivingDate ? null : __('No estimated delivery date'),
                'timestamp' => $estimatedReceivingDate,
            ];
        }

        return $timeline;
    }

    private function hasActiveStockDelivery(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->stockDeliveries()
            ->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)
            ->exists();
    }

    public function getDeliveryStats(PurchaseOrder $purchaseOrder): array
    {
        $progression = [
            PurchaseOrderDeliveryStateEnum::IN_PROCESS->value,
            PurchaseOrderDeliveryStateEnum::CONFIRMED->value,
            PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
            PurchaseOrderDeliveryStateEnum::DISPATCHED->value,
            PurchaseOrderDeliveryStateEnum::RECEIVED->value,
            PurchaseOrderDeliveryStateEnum::CHECKED->value,
            PurchaseOrderDeliveryStateEnum::PLACED->value,
        ];

        $rank = array_search($purchaseOrder->delivery_state->value, $progression, true);
        $hasStockDelivery = $this->hasActiveStockDelivery($purchaseOrder);

        return [
            'total_delivery_items'     => $hasStockDelivery ? (int) $purchaseOrder->stockDeliveries()->sum('number_stock_delivery_items_except_cancelled') : null,
            'total_placed_items'       => $hasStockDelivery ? (int) $purchaseOrder->stockDeliveries()->sum('number_stock_delivery_items_state_placed') : null,
            'is_delivery_items_active' => $hasStockDelivery,
            'is_placed_items_active'   => $rank !== false && $rank >= array_search(PurchaseOrderDeliveryStateEnum::RECEIVED->value, $progression, true),
        ];
    }

    public function getStockDeliveryTimelines(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->stockDeliveries()
            ->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)
            ->get()->map(fn (StockDelivery $stockDelivery) => [
            'reference'  => $stockDelivery->reference,
            'state'      => $stockDelivery->state->value,
            'state_icon' => StockDeliveryStateEnum::stateIcon()[$stockDelivery->state->value],
            'route'      => [
                'name'       => 'grp.org.procurement.stock_deliveries.show',
                'parameters' => [
                    'organisation'  => $purchaseOrder->organisation->slug,
                    'stockDelivery' => $stockDelivery->slug,
                ],
            ],
        ])->all();
    }

    public function getPrevious(PurchaseOrder $purchaseOrder, ActionRequest $request): ?array
    {
        $previous = $this->siblingPurchaseOrders($purchaseOrder, $request)->where('reference', '<', $purchaseOrder->reference)->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PurchaseOrder $purchaseOrder, ActionRequest $request): ?array
    {
        $next = $this->siblingPurchaseOrders($purchaseOrder, $request)->where('reference', '>', $purchaseOrder->reference)->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function siblingPurchaseOrders(PurchaseOrder $purchaseOrder, ActionRequest $request): Builder
    {
        $query = PurchaseOrder::where('organisation_id', $purchaseOrder->organisation_id);

        if ($request->route()->getName() !== 'grp.org.procurement.purchase_orders.show') {
            $query->where('parent_type', $purchaseOrder->parent_type)->where('parent_id', $purchaseOrder->parent_id);
        }

        return $query;
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
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
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
