<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Models\SupplyChain\AgentPayment;
use App\Actions\SupplyChain\AgentInvoice\ApproveAgentInvoiceCharges;
use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DistributeStockDeliveryExtraCost;
use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveryInvoiceCosting;
use App\Actions\GoodsIn\StockDelivery\Traits\WithStockDeliveryWeightAndVolume;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryItems;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryUnderOverDeliveredItems;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AgentInvoice\UI\GetAgentContainerInvoiceData;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Http\Resources\Procurement\OrgPartnerResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Http\Resources\Procurement\StockDeliveryItemCostResource;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Http\Resources\Procurement\StockDeliveryUnderOverDeliveredItemResource;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStockDelivery extends OrgAction
{
    use WithStockDeliveryWeightAndVolume;
    use WithAgentOrganisation;
    use WithGoodsInBookInAuthorisation;

    private bool $canEditPayments = false;
    private bool $canUpdateCosting = false;
    private bool $canBookIn = false;
    private bool $canUnreceive = false;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit          = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $isAgent                = $this->organisation->type === OrganisationTypeEnum::AGENT;
        $this->canEditPayments  = !$isAgent && ($this->canEdit || $request->user()->authTo("accounting.{$this->organisation->id}.edit"));
        $this->canUpdateCosting = !$isAgent && $request->user()->authTo("org-supervisor.{$this->organisation->id}.accounting");
        $this->canBookIn        = $this->authToBookIn($request, 'incoming.%d.edit');
        $this->canUnreceive     = $this->authToBookIn($request, 'supervisor-incoming.%d');

        return true;
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        return $stockDelivery;
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($organisation, $request)->withTab($this->getTabs($stockDelivery));
        $this->authorizeProcurementRecord($stockDelivery);

        return $this->handle($stockDelivery);
    }

    /**
     * Read-only view from the partner's shopping side.
     */
    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($organisation, $request)->withTab($this->getTabs($stockDelivery));
        $this->authorizeProcurementRecord($stockDelivery);
        $this->canEdit          = false;
        $this->canEditPayments  = false;
        $this->canUpdateCosting = false;
        $this->canBookIn        = false;
        $this->canUnreceive     = false;

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $this->validateAttributes();

        return Inertia::render(
            'Procurement/StockDelivery',
            [
                'title'            => __('Stock Delivery'),
                'breadcrumbs'      => $this->getBreadcrumbs($stockDelivery, $request->route()->originalParameters()),
                'pageHead'         => [
                    'title'      => $stockDelivery->reference,
                    'model'      => __('Stock Delivery'),
                    'icon'       => [
                        'icon'  => ['fal', 'people-arrows'],
                        'title' => __('Stock Delivery'),
                    ],
                    'afterTitle' => [
                        'label' => $this->getStateLabels($stockDelivery)[$stockDelivery->state->value]
                            .($stockDelivery->isManagedByPartner() && $stockDelivery->state !== StockDeliveryStateEnum::DISPATCHED ? ' · '.__('Managed by :partner until dispatched', ['partner' => $stockDelivery->parent?->partner?->name]) : ''),
                    ],
                    'edit'       => $this->canEdit && !$stockDelivery->isManagedByPartner() ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                    ] : false,
                    'actions'    => array_merge($this->getUpdateCostingActions($stockDelivery), $this->getAllowedActions($stockDelivery)),
                ],
                'stock_delivery'   => StockDeliveryResource::make($stockDelivery)->toArray($request),
                'timelines'        => $this->getTimeline($stockDelivery),
                'box_stats'        => $this->getBoxStats($stockDelivery, $request),
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsNavigation($stockDelivery),
                ],
                'costing'          => $this->getCosting($stockDelivery),
                'attachmentScopes' => PurchaseOrderAttachmentScopeEnum::options(),
                'invoice_costing'  => GetStockDeliveryInvoiceCosting::run($stockDelivery),
                'agentInvoice'     => $stockDelivery->agent_id && $stockDelivery->agent_id === $this->getOrganisationAgent($this->organisation)?->id
                    ? GetAgentContainerInvoiceData::run($this->organisation, $stockDelivery)
                    : null,
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.stock-delivery.attachment.attach',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                    'detachRoute' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.stock-delivery.attachment.detach',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],

                StockDeliveryTabsEnum::SHOWCASE->value => $this->tab == StockDeliveryTabsEnum::SHOWCASE->value ?
                    fn () => GetStockDeliveryData::run($stockDelivery)
                    : Inertia::optional(fn () => GetStockDeliveryData::run($stockDelivery)),

                StockDeliveryTabsEnum::ITEMS->value => $this->tab == StockDeliveryTabsEnum::ITEMS->value ?
                    fn () => $this->getItems($stockDelivery)
                    : Inertia::optional(fn () => $this->getItems($stockDelivery)),

                StockDeliveryTabsEnum::PENDING_ITEMS->value => $this->tab == StockDeliveryTabsEnum::PENDING_ITEMS->value ?
                    fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::PENDING_ITEMS->value, stateFilter: $this->pendingItemStates()))
                    : Inertia::optional(fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::PENDING_ITEMS->value, stateFilter: $this->pendingItemStates()))),

                StockDeliveryTabsEnum::DONE_ITEMS->value => $this->tab == StockDeliveryTabsEnum::DONE_ITEMS->value ?
                    fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::DONE_ITEMS->value, stateFilter: $this->doneItemStates()))
                    : Inertia::optional(fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::DONE_ITEMS->value, stateFilter: $this->doneItemStates()))),

                StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value => $this->tab == StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value ?
                    fn () => StockDeliveryUnderOverDeliveredItemResource::collection(IndexStockDeliveryUnderOverDeliveredItems::run($stockDelivery, StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))
                    : Inertia::optional(fn () => StockDeliveryUnderOverDeliveredItemResource::collection(IndexStockDeliveryUnderOverDeliveredItems::run($stockDelivery, StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))),

                StockDeliveryTabsEnum::PURCHASE_ORDERS->value => $this->tab == StockDeliveryTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($stockDelivery, StockDeliveryTabsEnum::PURCHASE_ORDERS->value))
                    : Inertia::optional(fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($stockDelivery, StockDeliveryTabsEnum::PURCHASE_ORDERS->value))),

                StockDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == StockDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))),

                StockDeliveryTabsEnum::NOTES->value => $this->tab == StockDeliveryTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($stockDelivery, StockDeliveryTabsEnum::NOTES->value))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($stockDelivery, StockDeliveryTabsEnum::NOTES->value))),

                'note_store_route' => [
                    'name'       => 'grp.models.stock-delivery.note.store',
                    'parameters' => [$stockDelivery->id],
                ],

                StockDeliveryTabsEnum::HISTORY->value => $this->tab == StockDeliveryTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value, auditScope: $this->historyAuditScope($stockDelivery)))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value, auditScope: $this->historyAuditScope($stockDelivery)))),
            ]
        )->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::ITEMS->value))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::PENDING_ITEMS->value))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::DONE_ITEMS->value))
            ->table(IndexStockDeliveryUnderOverDeliveredItems::make()->tableStructure(prefix: StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))
            ->table(IndexPurchaseOrders::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::PURCHASE_ORDERS->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: StockDeliveryTabsEnum::ATTACHMENTS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: StockDeliveryTabsEnum::NOTES->value))
            ->table(IndexHistory::make()->tableStructure(prefix: StockDeliveryTabsEnum::HISTORY->value));
    }

    public function jsonResponse(): StockDeliveryResource
    {
        return new StockDeliveryResource($this->stockDelivery);
    }

    public function getPurchaseOrderTimeline(EloquentCollection $purchaseOrders): array
    {
        $labels = PurchaseOrderStateEnum::labels();

        $states = [
            PurchaseOrderStateEnum::IN_PROCESS->value => $purchaseOrders->pluck('created_at')->filter()->min(),
            PurchaseOrderStateEnum::SUBMITTED->value  => $purchaseOrders->pluck('submitted_at')->filter()->min(),
        ];

        $timeline = [];

        foreach ($states as $state => $timestamp) {
            $key = 'purchase_order_' . $state;

            $timeline[$key] = [
                'label'       => $labels[$state],
                'tooltip'     => __('Purchase Order') . ': ' . $labels[$state],
                'key'         => $key,
                'icon'        => 'fal fa-clipboard-list',
                'format_time' => 'MMMM d yyyy, HH:mm',
                'timestamp'   => $timestamp,
            ];
        }

        return $timeline;
    }

    private function getUpdateCostingActions(StockDelivery $stockDelivery): array
    {
        if (!$this->canUpdateCosting || $stockDelivery->state !== StockDeliveryStateEnum::PLACED || $stockDelivery->parent_type === 'OrgPartner') {
            return [];
        }

        if ($stockDelivery->is_costed) {
            return [
                [
                    'label'   => __('Update costing'),
                    'tooltip' => __('Correct the costs of this delivery, it changes the value of the stock put away'),
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'icon'    => 'fal fa-edit',
                    'key'     => 'reopen_stock_delivery_costing',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.reopen-costing',
                        'parameters' => ['stockDelivery' => $stockDelivery->id],
                    ],
                ],
            ];
        }

        if (Arr::has($stockDelivery->data, 'costing_reopened')) {
            return [
                [
                    'label'   => __('Finish costing'),
                    'tooltip' => __('Revalue the stock put away and rebuild its stock history'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-check',
                    'key'     => 'finish_stock_delivery_costing',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.finish-costing',
                        'parameters' => ['stockDelivery' => $stockDelivery->id],
                    ],
                ],
            ];
        }

        return [];
    }

    private function historyAuditScope(StockDelivery $stockDelivery): array
    {
        return [
            [
                'type'   => $stockDelivery->getMorphClass(),
                'labels' => [$stockDelivery->id => $stockDelivery->reference],
                'shops'  => null,
            ],
            [
                'type'   => 'StockDeliveryItem',
                'labels' => $stockDelivery->items()->with('orgStock:id,code')->get()
                    ->mapWithKeys(fn ($item) => [$item->id => $item->orgStock?->code ?? '#'.$item->id])
                    ->all(),
                'shops'  => null,
            ],
        ];
    }

    public function getActions(StockDelivery $stockDelivery): array
    {
        $hasPlacements = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->where('unit_quantity_placed', '>', 0)
            ->exists();

        $pdfButton = [
            'type'   => 'button',
            'style'  => 'tertiary',
            'label'  => 'PDF',
            'target' => '_blank',
            'icon'   => 'fal fa-file-pdf',
            'key'    => 'action',
            'route'  => [
                'name'       => 'grp.org.procurement.stock_deliveries.pdf',
                'parameters' => [
                    'organisation'  => (request()->route('organisation') ?? $stockDelivery->organisation)->slug,
                    'stockDelivery' => $stockDelivery->slug,
                ],
            ],
        ];

        $cancelButton = [
            'label'   => __('Cancel'),
            'tooltip' => __('Cancel Stock Delivery'),
            'type'    => 'button',
            'style'   => 'delete',
            'icon'    => 'fal fa-times-circle',
            'key'     => 'cancel_stock_delivery',
            'route'   => [
                'method'     => 'patch',
                'name'       => 'grp.models.stock-delivery.cancel',
                'parameters' => [
                    'stockDelivery' => $stockDelivery->id,
                ],
            ],
        ];

        $actions = match ($stockDelivery->state) {
            StockDeliveryStateEnum::IN_PROCESS,
            StockDeliveryStateEnum::CONFIRMED,
            StockDeliveryStateEnum::READY_TO_SHIP => [
                [
                    'label'   => __('Mark as Dispatched'),
                    'tooltip' => __('Mark Stock Delivery as Dispatched'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-truck',
                    'key'     => 'dispatch_stock_delivery',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.dispatch',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
                [
                    'label'   => __('Mark as Received'),
                    'tooltip' => __('Mark Stock Delivery as Received'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-check',
                    'key'     => 'receive_stock_delivery',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.receive',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
                [
                    'label'   => __('Delete'),
                    'tooltip' => __('Delete Stock Delivery'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'icon'    => 'fal fa-trash-alt',
                    'key'     => 'delete_stock_delivery',
                    'route'   => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.stock-delivery.delete',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
            ],
            StockDeliveryStateEnum::DISPATCHED => [
                [
                    'label'   => __('Mark as Received'),
                    'tooltip' => __('Mark Stock Delivery as Received'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-check',
                    'key'     => 'receive_stock_delivery',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.receive',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
                [
                    'label'   => __('Unmark as Dispatched'),
                    'tooltip' => __('Revert Stock Delivery to its previous state'),
                    'type'    => 'button',
                    'style'   => 'cancel',
                    'icon'    => 'fal fa-undo',
                    'key'     => 'undispatch_stock_delivery',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.undispatch',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
            ],
            StockDeliveryStateEnum::RECEIVED => [
                [
                    'label'   => __('Unmark as Received'),
                    'tooltip' => __('Revert Stock Delivery to its previous state'),
                    'type'    => 'button',
                    'style'   => 'cancel',
                    'icon'    => 'fal fa-undo',
                    'key'     => 'unreceive_stock_delivery',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.unreceive',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
                $cancelButton,
            ],
            StockDeliveryStateEnum::CHECKED => $hasPlacements ? [] : [
                $cancelButton,
            ],
            StockDeliveryStateEnum::BOOKED_IN => [
                [
                    'label'   => __('Place'),
                    'tooltip' => __('Place this stock delivery, this is its final state'),
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-box-usd',
                    'key'     => 'start_stock_delivery_costing',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.start-costing',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],
            ],
            default => [],
        };

        if (CancelStockDelivery::canBeCancelled($stockDelivery) && $stockDelivery->hasNoProducts()) {
            $actions = array_values(array_filter($actions, fn (array $action) => !in_array($action['key'], ['dispatch_stock_delivery', 'receive_stock_delivery', 'cancel_stock_delivery'])));
            array_unshift($actions, $cancelButton);
        }

        if ($stockDelivery->isManagedByPartner()) {
            $actions = $stockDelivery->state === StockDeliveryStateEnum::DISPATCHED
                ? array_values(array_filter($actions, fn (array $action) => $action['key'] === 'receive_stock_delivery'))
                : [];
        }

        return array_merge($actions, [$pdfButton]);
    }

    private function getAllowedActions(StockDelivery $stockDelivery): array
    {
        if ($this->canEdit) {
            return $this->getActions($stockDelivery);
        }

        if (!$this->canBookIn || $stockDelivery->organisation_id !== $this->organisation->id) {
            return [];
        }

        $allowedKeys = $this->canUnreceive
            ? ['receive_stock_delivery', 'unreceive_stock_delivery', 'action']
            : ['receive_stock_delivery', 'action'];

        return array_values(array_filter(
            $this->getActions($stockDelivery),
            fn (array $action) => in_array($action['key'], $allowedKeys)
        ));
    }

    public function getStateLabels(StockDelivery $stockDelivery): array
    {
        return StockDeliveryStateEnum::labels();
    }

    public function getBoxStats(StockDelivery $stockDelivery, ActionRequest $request): array
    {
        $orderer = [];
        if ($stockDelivery->parent instanceof OrgAgent) {
            $orderer = OrgAgentResource::make($stockDelivery->parent)->toArray($request);
        } elseif ($stockDelivery->parent instanceof OrgSupplier) {
            $orderer = OrgSupplierResource::make($stockDelivery->parent)->toArray($request);
        } elseif ($stockDelivery->parent instanceof OrgPartner) {
            $orderer = OrgPartnerResource::make($stockDelivery->parent)->toArray($request);
        }

        $weightAndVolume = $this->getStockDeliveryWeightAndVolume($stockDelivery);

        return [
            'first_block'  => [
                'orderer'  => $orderer,
                'delivery' => [
                    'type'             => Arr::get($stockDelivery->data, 'delivery_type'),
                    'incoterm'         => Arr::get($stockDelivery->data, 'incoterm'),
                    'port_of_export'   => Arr::get($stockDelivery->data, 'port_of_export'),
                    'port_of_import'   => Arr::get($stockDelivery->data, 'port_of_import'),
                    'delivery_address' => Arr::get($stockDelivery->data, 'delivery_address'),
                ],
            ],
            'second_block' => [
                'state'                        => $this->getStateLabels($stockDelivery)[$stockDelivery->state->value],
                'total_items'                  => $stockDelivery->number_stock_delivery_items,
                'total_received_checked_items' => $stockDelivery->number_stock_delivery_items_state_received + $stockDelivery->number_stock_delivery_items_state_checked,
                'total_placed_items'           => $stockDelivery->number_stock_delivery_items_state_placed,
                'total_new_org_stocks'         => $stockDelivery->items()
                    ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED])
                    ->whereHas('orgStock', fn ($query) => $query->where('has_been_in_warehouse', false))
                    ->distinct()
                    ->count('org_stock_id'),
                'show_delivery_discrepancy'    => $stockDelivery->checked_at !== null,
                'total_under_delivered_items'  => $stockDelivery->number_stock_delivery_items_under_delivered,
                'total_over_delivered_items'   => $stockDelivery->number_stock_delivery_items_over_delivered,
                'weight'                       => Arr::get($weightAndVolume, 'gross_weight'),
                'volume'                       => Arr::get($weightAndVolume, 'volume'),
                'is_weight_partial'            => Arr::get($weightAndVolume, 'is_weight_partial'),
                'is_volume_partial'            => Arr::get($weightAndVolume, 'is_volume_partial'),
                'production_time'              => null, // Todo: not sure in which states this should appear, so far only known when the purchase order is cancelled
                'delivery_time'                => null, // Todo: not sure in which states this should appear, so far only known when the purchase order is cancelled
            ],
            'third_block'  => [
                'currency'     => $stockDelivery->currency?->code,
                'org_currency' => $stockDelivery->organisation?->currency?->code,
                'org_exchange' => $stockDelivery->org_exchange,
                'items'        => $stockDelivery->cost_items,
                'extra'        => $stockDelivery->cost_extra,
                'shipping'     => $stockDelivery->cost_shipping,
                'duties'       => $stockDelivery->cost_duties,
                'tax'          => $stockDelivery->cost_tax,
                'total'        => $stockDelivery->cost_total,
                'org_items'    => $stockDelivery->items()->sum('org_net_amount'),
            ],
            'invoice' => $this->getInvoiceSummary($stockDelivery),
            'invoice_entry' => $this->canEdit && $this->canEditPayments && !$stockDelivery->isManagedByPartner() && ($stockDelivery->agent_id ? $stockDelivery->agentInvoice()->first()?->source : null) !== StockDeliveryInvoiceSourceEnum::AGENT ? [
                'route' => [
                    'name'       => 'grp.models.stock-delivery.invoice.store',
                    'parameters' => [$stockDelivery->id],
                ],
                'is_agent' => (bool) $stockDelivery->agent_id,
            ] : null,
        ];
    }

    /**
     * @return array{kind: string, source: string, reference: string|null, date: string, charges_list: array<int, array<string, mixed>>, currency: string, org_currency: string, org_exchange: float|null, goods: float, charges: float, total: float, paid: float|null, balance_due: float|null}|null
     */
    public function getInvoiceSummary(StockDelivery $stockDelivery): ?array
    {
        $invoice = $stockDelivery->agentInvoice()->with('currency')->first()
            ?? $stockDelivery->supplierInvoice()->with('currency')->first();

        if (!$invoice) {
            return null;
        }

        $orgCurrency = $stockDelivery->organisation->currency;

        $orgExchange = match (true) {
            $invoice->currency_id === $orgCurrency->id => 1.0,
            $invoice->currency_id === $stockDelivery->currency_id && (float) $stockDelivery->org_exchange > 0 => (float) $stockDelivery->org_exchange,
            default => GetCurrencyExchange::run($invoice->currency, $orgCurrency),
        };

        $isAgentInvoice = $invoice instanceof AgentInvoice;

        return [
            'kind'         => $isAgentInvoice ? 'agent' : 'supplier',
            'source'       => $invoice->source->value,
            'reference'    => $invoice->reference,
            'date'         => $invoice->date->toDateString(),
            'charges_list' => $invoice->charges ?? [],
            'currency'     => $invoice->currency->code,
            'org_currency' => $orgCurrency->code,
            'org_exchange' => $orgExchange,
            'goods'        => (float) $invoice->goods_amount,
            'charges'      => (float) $invoice->charges_amount,
            'total'        => (float) $invoice->total_amount,
            'paid'         => $isAgentInvoice ? $invoice->paidAmount() : null,
            'balance_due'  => $isAgentInvoice ? $invoice->balanceDue() : null,
            'agent'        => $isAgentInvoice && ($this->organisation ?? null)?->type !== OrganisationTypeEnum::AGENT ? [
                'can_edit'            => $this->canEditPayments,
                'charges_approved'    => ApproveAgentInvoiceCharges::isApproved($invoice),
                'approve_route'       => ['name' => 'grp.models.stock-delivery.agent_invoice.approve_charges', 'parameters' => ['stockDelivery' => $stockDelivery->id]],
                'deposits'            => array_values(array_filter($invoice->advancePayments(), fn (array $payment) => $payment['type'] === 'deposit')),
                'payments'            => $stockDelivery->agentPayments()->orderBy('date')->get()->map(fn (AgentPayment $payment) => [
                    'id'           => $payment->id,
                    'date'         => $payment->date->toDateString(),
                    'amount'       => (float) $payment->amount,
                    'reference'    => $payment->reference,
                    'notes'        => $payment->notes,
                    'delete_route' => ['name' => 'grp.models.agent_payment.delete', 'parameters' => ['agentPayment' => $payment->id]],
                ])->values()->all(),
                'payment_store_route' => ['name' => 'grp.models.stock-delivery.agent_payment.store', 'parameters' => ['stockDelivery' => $stockDelivery->id]],
            ] : null,
        ];
    }

    public function getTimeline(StockDelivery $stockDelivery, bool $withPurchaseOrderStates = true): array
    {
        $purchaseOrders = $withPurchaseOrderStates ? $stockDelivery->purchaseOrders()->get() : new EloquentCollection();

        $timeline = $purchaseOrders->isNotEmpty() ? $this->getPurchaseOrderTimeline($purchaseOrders) : [];

        $labels = $this->getStateLabels($stockDelivery);

        $hiddenUnlessCurrent = [
            StockDeliveryStateEnum::CONFIRMED,
            StockDeliveryStateEnum::READY_TO_SHIP,
            StockDeliveryStateEnum::BOOKING_IN,
            StockDeliveryStateEnum::CANCELLED,
            StockDeliveryStateEnum::NOT_RECEIVED,
        ];

        foreach (StockDeliveryStateEnum::cases() as $case) {
            $timestamp = match ($case) {
                StockDeliveryStateEnum::IN_PROCESS    => $stockDelivery->created_at,
                StockDeliveryStateEnum::CONFIRMED     => $stockDelivery->confirmed_at,
                StockDeliveryStateEnum::READY_TO_SHIP => $stockDelivery->ready_to_ship_at,
                StockDeliveryStateEnum::BOOKING_IN    => $stockDelivery->booking_in_at,
                StockDeliveryStateEnum::BOOKED_IN     => $stockDelivery->booked_in_at,
                default                               => $stockDelivery->{$case->snake() . '_at'} ?: null
            };

            if (in_array($case, $hiddenUnlessCurrent, true) && $stockDelivery->state != $case && !$timestamp) {
                continue;
            }

            if ($stockDelivery->state === StockDeliveryStateEnum::CANCELLED && $case !== StockDeliveryStateEnum::CANCELLED && !$timestamp) {
                continue;
            }

            $estimatedTimestamp = $timestamp ? null : match ($case) {
                StockDeliveryStateEnum::DISPATCHED => Arr::get($stockDelivery->data, 'estimated_dispatched_date'),
                StockDeliveryStateEnum::RECEIVED   => Arr::get($stockDelivery->data, 'estimated_receiving_date'),
                default                            => null
            };

            $label = $case == StockDeliveryStateEnum::IN_PROCESS && $purchaseOrders->isNotEmpty()
                ? __('Created')
                : $labels[$case->value];

            $timeline[$case->value] = [
                'label'             => $label,
                'tooltip'           => $labels[$case->value],
                'key'               => $case->value,
                'format_time'       => $estimatedTimestamp ? 'MMMM d yyyy' : 'MMMM d yyyy, HH:mm',
                'timestamp'         => $timestamp ?: $estimatedTimestamp,
                'timestamp_icon'    => $estimatedTimestamp ? 'fas fa-thumbtack' : null,
                'timestamp_tooltip' => $estimatedTimestamp ? __('Estimated') : null,
            ];
        }

        return $timeline;
    }

    public function getBreadcrumbs(StockDelivery $stockDelivery, array $routeParameters, string $suffix = ''): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Supplier delivery'),
                            'route' => [
                                'name' => 'grp.org.procurement.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                        ],
                        'model' => [
                            'label' => $stockDelivery->reference,
                            'route' => [
                                'name'       => 'grp.org.procurement.stock_deliveries.show',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                    'suffix' => $suffix,
                ],
            ],
        );
    }

    private function pendingItemStates(): array
    {
        return StockDeliveryItemStateEnum::valuesExcept([
            StockDeliveryItemStateEnum::PLACED->value,
            StockDeliveryItemStateEnum::CANCELLED->value,
        ]);
    }

    private function doneItemStates(): array
    {
        return [StockDeliveryItemStateEnum::PLACED->value, StockDeliveryItemStateEnum::CANCELLED->value];
    }

    private function getItems(StockDelivery $stockDelivery): AnonymousResourceCollection
    {
        if ($stockDelivery->state === StockDeliveryStateEnum::PLACED) {
            return StockDeliveryItemCostResource::collection(
                IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value, numberOfRecords: config('ui.table.max_records_per_page'))
            );
        }

        return StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value));
    }

    private function getCosting(StockDelivery $stockDelivery): array
    {
        $costs = $stockDelivery->costs()->orderBy('id')->get();

        $checklist = [];
        foreach ([StockDeliveryCostTypeEnum::AGENT_INVOICE, StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row         = $costs->firstWhere('type', $type);
            $checklist[] = $this->costRow($type, $row);
        }
        foreach ($costs->where('type', StockDeliveryCostTypeEnum::EXTRA) as $row) {
            $checklist[] = $this->costRow(StockDeliveryCostTypeEnum::EXTRA, $row);
        }

        $agentInvoice = $costs->firstWhere('type', StockDeliveryCostTypeEnum::AGENT_INVOICE);

        $applications  = $stockDelivery->depositApplications()->orderBy('id')->get();
        $depositsTotal = (float) $applications->sum('amount');
        $agentInvoiceAmount = (float) ($agentInvoice?->amount ?? 0);

        return [
            'is_costed'                  => $stockDelivery->is_costed,
            'is_partner'                 => $stockDelivery->parent_type === 'OrgPartner',
            'can_edit'                   => !$stockDelivery->is_costed && (Arr::has($stockDelivery->data, 'costing_reopened') ? $this->canUpdateCosting : ($this->canEditPayments || $this->canUpdateCosting)),
            'reopened'                   => $this->getCostingReopened($stockDelivery),
            'unbalanced'                 => $stockDelivery->state === StockDeliveryStateEnum::PLACED && !$stockDelivery->is_costed ? EvaluateStockDeliveryCosting::unbalancedHandSplits($stockDelivery) : [],
            'can_edit_payments'          => $this->canEditPayments,
            'currency'                   => $stockDelivery->currency?->code,
            'currency_id'                => $stockDelivery->currency_id,
            'org_currency'               => $stockDelivery->organisation->currency->code,
            'org_currency_id'            => $stockDelivery->organisation->currency_id,
            'org_exchange'               => $stockDelivery->org_exchange,
            'updateRoute'                => [
                'name'       => 'grp.models.stock-delivery.update',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'patch',
            ],
            'currencies'                 => Currency::orderBy('code')->get(['id', 'code'])->toArray(),
            'checklist'                  => $checklist,
            'shipping_basis'             => DistributeStockDeliveryExtraCost::shippingBasis($stockDelivery),
            'agent_invoice_missing'      => !$agentInvoice?->received_at,
            'storeCostRoute'             => [
                'name'       => 'grp.models.stock-delivery.cost.store',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'post',
            ],
            'distributeExtraCostRoute'   => $stockDelivery->state === StockDeliveryStateEnum::PLACED && !$stockDelivery->is_costed ? [
                'name'       => 'grp.models.stock-delivery.distribute-extra-cost',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'patch',
            ] : null,
            'deposits'                    => $this->getDepositSettlement($stockDelivery, $applications, $agentInvoiceAmount, $depositsTotal),
            'partner_invoice'             => $this->getPartnerInvoice($stockDelivery),
        ];
    }

    /**
     * The seller's invoice for a partner delivery with the refunds the seller has made on it, read live so a
     * refund shows here as soon as the partner finalises it, against what did not arrive.
     *
     * @return array{reference: string, net_amount: float, refunds: array<int, array{reference: string, date: mixed, net_amount: float}>, refunded: float, missing_amount: float, to_refund: float}|null
     */
    private function getPartnerInvoice(StockDelivery $stockDelivery): ?array
    {
        if ($stockDelivery->parent_type !== 'OrgPartner' || !$stockDelivery->invoice_id) {
            return null;
        }

        $invoice = Invoice::where('id', $stockDelivery->invoice_id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->where('currency_id', $stockDelivery->currency_id)
            ->first();
        if (!$invoice) {
            return null;
        }

        $refunds = Invoice::where('original_invoice_id', $invoice->id)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->where('in_process', false)
            ->orderBy('date')
            ->get(['reference', 'date', 'net_amount']);

        $refunded = round(-(float) $refunds->sum('net_amount'), 2);

        $missingAmount = round((float) $stockDelivery->items()
            ->whereNotIn('state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::IN_PROCESS, StockDeliveryItemStateEnum::CONFIRMED, StockDeliveryItemStateEnum::READY_TO_SHIP, StockDeliveryItemStateEnum::DISPATCHED, StockDeliveryItemStateEnum::RECEIVED])
            ->where('unit_quantity', '>', 0)
            ->whereColumn('unit_quantity_checked', '<', 'unit_quantity')
            ->sum(DB::raw('net_amount * (unit_quantity - unit_quantity_checked) / unit_quantity')), 2);

        return [
            'reference'      => $invoice->reference,
            'net_amount'     => (float) $invoice->net_amount,
            'refunds'        => $refunds->map(fn (Invoice $refund) => [
                'reference'  => $refund->reference,
                'date'       => $refund->date,
                'net_amount' => -(float) $refund->net_amount,
            ])->all(),
            'refunded'       => $refunded,
            'missing_amount' => $missingAmount,
            'to_refund'      => max(0, round($missingAmount - $refunded, 2)),
        ];
    }

    private function getCostingReopened(StockDelivery $stockDelivery): ?array
    {
        $reopened = Arr::get($stockDelivery->data, 'costing_reopened');
        if (!$reopened) {
            return null;
        }

        return [
            'at'     => Arr::get($reopened, 'at'),
            'by'     => User::find(Arr::get($reopened, 'user_id'))?->contact_name,
            'reason' => Arr::get($reopened, 'reason'),
        ];
    }

    private function getDepositSettlement(StockDelivery $stockDelivery, $applications, float $agentInvoiceAmount, float $depositsTotal): array
    {
        $availableDeposits = $stockDelivery->agent_id
            ? \App\Models\SupplyChain\AspoDeposit::applicableToStockDelivery($stockDelivery)
                ->where('state', 'paid_to_supplier')
                ->get()
                ->filter(fn ($deposit) => $deposit->unapplied_amount > 0)
            : collect();

        return [
            'applied'            => $applications->map(fn ($application) => [
                'id'     => $application->id,
                'amount' => $application->amount,
                'aspo_deposit_id' => $application->aspo_deposit_id,
                'reference' => $application->aspoDeposit?->reference,
                'deleteRoute' => $this->canEditPayments ? [
                    'name'       => 'grp.models.stock-delivery-deposit-application.delete',
                    'parameters' => ['stockDeliveryDepositApplication' => $application->id],
                    'method'     => 'delete',
                ] : null,
            ])->all(),
            'applied_total'      => $depositsTotal,
            'agent_invoice_amount' => $agentInvoiceAmount,
            'balance_due'        => $agentInvoiceAmount - $depositsTotal,
            'available'          => $availableDeposits->map(fn ($deposit) => [
                'id'                => $deposit->id,
                'reference'         => $deposit->reference,
                'unapplied_amount'  => $deposit->unapplied_amount,
                'currency_code'     => $deposit->currency->code,
            ])->values()->all(),
            'applyRoute'         => [
                'name'       => 'grp.models.stock-delivery.deposit.apply',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'post',
            ],
        ];
    }

    private function costRow(StockDeliveryCostTypeEnum $type, ?StockDeliveryCost $row): array
    {
        return [
            'id'          => $row?->id,
            'type'        => $type->value,
            'label'       => $row?->label ?: StockDeliveryCostTypeEnum::labels()[$type->value],
            'amount'      => $row?->amount,
            'received_at' => $row?->received_at,
            'is_na'       => (bool) $row?->is_na,
            'currency_id' => $row?->currency_id,
            'exchange'    => $row?->exchange,
            'updateRoute' => $row ? [
                'name'       => 'grp.models.stock-delivery-cost.update',
                'parameters' => ['stockDeliveryCost' => $row->id],
                'method'     => 'patch',
            ] : null,
            'deleteRoute' => $row && $type === StockDeliveryCostTypeEnum::EXTRA ? [
                'name'       => 'grp.models.stock-delivery-cost.delete',
                'parameters' => ['stockDeliveryCost' => $row->id],
                'method'     => 'delete',
            ] : null,
        ];
    }

    private function getTabsNavigation(StockDelivery $stockDelivery): array
    {
        $navigation = $this->hasUnderOverDeliveredTab($stockDelivery)
            ? StockDeliveryTabsEnum::navigation()
            : StockDeliveryTabsEnum::navigationExcept([StockDeliveryTabsEnum::UNDER_OVER_DELIVERED]);

        if (!$stockDelivery->purchaseOrders()->exists()) {
            unset($navigation[StockDeliveryTabsEnum::PURCHASE_ORDERS->value]);
        }

        if ($stockDelivery->state === StockDeliveryStateEnum::PLACED) {
            $navigation[StockDeliveryTabsEnum::ITEMS->value]['title'] = __('Items (costing)');
            $navigation[StockDeliveryTabsEnum::ITEMS->value]['icon']  = 'fal fa-box-usd';
        }

        return $navigation;
    }

    private function hasUnderOverDeliveredTab(StockDelivery $stockDelivery): bool
    {
        return in_array($stockDelivery->state, [StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED], true);
    }

    private function getTabs(StockDelivery $stockDelivery): array
    {
        $tabs = StockDeliveryTabsEnum::values();

        if ($this->hasUnderOverDeliveredTab($stockDelivery)) {
            return $tabs;
        }

        return array_values(array_diff($tabs, [StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value]));
    }
}
