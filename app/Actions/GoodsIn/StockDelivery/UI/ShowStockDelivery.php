<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDeliveryPackingCheck\UI\GetStockDeliveryPackingChecks;
use App\Models\SupplyChain\AgentPayment;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\SupplyChain\SupplierInvoice;
use App\Models\SupplyChain\SupplierPayment;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Actions\SupplyChain\AgentInvoice\ApproveAgentInvoiceCharges;
use App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrderItemsToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrdersToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DeleteStockDelivery;
use App\Actions\GoodsIn\StockDelivery\RemovePurchaseOrderFromStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DistributeStockDeliveryExtraCost;
use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\ReopenStockDeliveryCosting;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveryHandTypedCosts;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveryInvoiceCosting;
use App\Actions\GoodsIn\StockDelivery\PdfStockDelivery;
use App\Actions\GoodsIn\StockDelivery\RemoveStockDeliveryUnbackedCosts;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveryServiceInvoiceReadings;
use App\Actions\GoodsIn\StockDelivery\Traits\WithStockDeliveryWeightAndVolume;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemPackedInside;
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
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
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
use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\UI\IndexStockDeliveryServiceInvoices;
use App\Models\Helpers\Audit;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Media;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\Inventory\Warehouse;
use App\Actions\UI\Incoming\ShowIncomingHub;
use App\Actions\Traits\Authorisations\WithAttachmentEditAuthorisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStockDelivery extends OrgAction
{
    use WithStockDeliveryWeightAndVolume;
    use WithAgentOrganisation;
    use WithGoodsInBookInAuthorisation;
    use WithAttachmentEditAuthorisation;

    private bool $canEditPayments = false;
    private bool $canUpdateCosting = false;
    private bool $canBookIn = false;
    private bool $canUnreceive = false;
    private bool $viewedByAgent = false;
    private bool $goodsIn = false;
    private bool $canAddPurchaseOrders = false;
    private bool $canRemovePurchaseOrders = false;

    private const array BOOKING_IN_TABS = [
        StockDeliveryTabsEnum::PENDING_ITEMS,
        StockDeliveryTabsEnum::DONE_ITEMS,
    ];

    private const array PROCUREMENT_ONLY_TABS = [
        StockDeliveryTabsEnum::ITEMS,
        StockDeliveryTabsEnum::CUSTOMS,
        StockDeliveryTabsEnum::PURCHASE_ORDERS,
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->goodsIn) {
            $this->canBookIn    = $this->authToBookIn($request, 'incoming.%d.edit');
            $this->canUnreceive = $this->authToBookIn($request, 'supervisor-incoming.%d');

            return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
        }

        $this->canEdit          = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $isAgent                = $this->organisation->type === OrganisationTypeEnum::AGENT;
        $this->canEditPayments  = !$isAgent && ($this->canEdit || $request->user()->authTo("accounting.{$this->organisation->id}.edit"));
        $this->canUpdateCosting = !$isAgent && $this->stockDelivery->canUpdateCosting($request->user());
        $this->canBookIn        = $this->authToBookIn($request, 'incoming.%d.edit');
        $this->canUnreceive     = $this->authToBookIn($request, 'supervisor-incoming.%d');

        $canChangePurchaseOrders       = AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
        $this->canAddPurchaseOrders    = $canChangePurchaseOrders && AddPurchaseOrdersToStockDelivery::acceptsPurchaseOrders($this->stockDelivery);
        $this->canRemovePurchaseOrders = $canChangePurchaseOrders && RemovePurchaseOrderFromStockDelivery::acceptsRemovals($this->stockDelivery);

        return true;
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        return $stockDelivery;
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->viewedByAgent = $organisation->type === OrganisationTypeEnum::AGENT && $stockDelivery->organisation_id !== $organisation->id;
        $this->initialisation($organisation, $request);
        if (in_array($this->get('tab'), array_column(self::BOOKING_IN_TABS, 'value'), true)) {
            $this->set('tab', null);
        }
        $this->withTab($this->getTabs($stockDelivery));
        $agentCanEdit = $this->canEdit;
        $this->authorizeProcurementRecord($stockDelivery);
        if ($this->viewedByAgent && $this->organisationAgentSupplies($stockDelivery)) {
            $this->canEdit = $agentCanEdit;
        }

        return $this->handle($stockDelivery);
    }

    /**
     * The warehouse's page of a delivery: booking in, with no costing to edit.
     */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        abort_unless($stockDelivery->organisation_id === $warehouse->organisation_id, 404);

        $this->stockDelivery = $stockDelivery;
        $this->goodsIn       = true;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab($this->getTabs($stockDelivery));

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
        $this->canAddPurchaseOrders = false;
        $this->canRemovePurchaseOrders = false;

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $editRoute = match (true) {
            !$this->canEdit || $this->goodsIn || $stockDelivery->isManagedByPartner() => null,
            $this->viewedByAgent => [
                'name'       => 'grp.org.agent.stock_deliveries.edit',
                'parameters' => [$this->organisation->slug, $stockDelivery->slug],
            ],
            $stockDelivery->organisation_id === $this->organisation->id => [
                'name'       => 'grp.org.procurement.stock_deliveries.edit',
                'parameters' => [$this->organisation->slug, $stockDelivery->slug],
            ],
            default => null,
        };

        $isContainer = DeleteStockDelivery::isAgentDelivery($stockDelivery);
        $modelLabel  = match (true) {
            $this->viewedByAgent => __('Shipment'),
            $isContainer         => __('Container'),
            default              => __('Stock Delivery'),
        };

        $missingEvidence = in_array($stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP])
            ? DispatchStockDelivery::missingEvidence($stockDelivery)
            : [];

        return Inertia::render(
            'Procurement/StockDelivery',
            [
                'title'            => '('.Str::limit($stockDelivery->reference, 12).') '.$modelLabel,
                'is_container'     => $isContainer,
                'breadcrumbs'      => $this->goodsIn
                    ? $this->getGoodsInBreadcrumbs($stockDelivery, $request->route()->originalParameters())
                    : $this->getBreadcrumbs($stockDelivery, $request->route()->originalParameters()),
                'pageHead'         => [
                    'title'      => $stockDelivery->reference,
                    'model'      => $modelLabel,
                    'icon'       => [
                        'icon'  => ['fal', $isContainer ? 'truck-container' : 'people-arrows'],
                        'title' => $modelLabel,
                    ],
                    'afterTitle' => [
                        'label' => $this->getStateLabels($stockDelivery)[$stockDelivery->state->value]
                            .($stockDelivery->isManagedByPartner() && $stockDelivery->state !== StockDeliveryStateEnum::DISPATCHED ? ' · '.__('Managed by :partner until dispatched', ['partner' => $stockDelivery->parent?->partner?->name]) : ''),
                    ],
                    'edit'       => $editRoute ? ['route' => $editRoute] : false,
                    'actions'    => $this->arrangePageHeadActions(array_merge($this->getUpdateCostingActions($stockDelivery), $this->getBookInLinkAction($stockDelivery, $request), $this->getAllowedActions($stockDelivery), $editRoute ? [['type' => 'button', 'style' => 'edit', 'label' => __('Settings'), 'key' => 'settings', 'route' => $editRoute]] : [])),
                ],
                'stock_delivery'   => StockDeliveryResource::make($stockDelivery)->toArray($request),
                'timelines'        => $this->getTimeline($stockDelivery),
                'box_stats'        => $this->getBoxStats($stockDelivery, $request),
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsNavigation($stockDelivery, $missingEvidence),
                ],
                'costing'          => $this->goodsIn ? null : $this->getCosting($stockDelivery),
                'missing_evidence' => $missingEvidence,
                'add_purchase_orders' => $this->canAddPurchaseOrders ? $this->getAddPurchaseOrders($stockDelivery) : null,
                'add_purchase_order_items' => $this->canAddPurchaseOrders ? Inertia::optional(fn () => $this->getAddPurchaseOrderItems($stockDelivery)) : null,
                'remove_purchase_orders' => $this->canRemovePurchaseOrders ? $this->getRemovePurchaseOrders($stockDelivery) : null,
                'attachmentScopes' => PurchaseOrderAttachmentScopeEnum::options(),
                'invoice_costing'  => $this->viewedByAgent || $this->goodsIn ? [] : GetStockDeliveryInvoiceCosting::run($stockDelivery),
                'service_invoices' => $this->goodsIn ? null : $this->getServiceInvoices($stockDelivery),
                'agreed_term_differences' => $this->viewedByAgent || $this->goodsIn ? null : CheckAgainstAgreedTerms::make()->forPage($stockDelivery),
                'agentInvoice'     => !$this->goodsIn && $stockDelivery->agent_id && $stockDelivery->agent_id === $this->getOrganisationAgent($this->organisation)?->id
                    ? GetAgentContainerInvoiceData::run($this->organisation, $stockDelivery)
                    : null,
                'attachmentRoutes' => [
                    'canChange'   => !$this->goodsIn && $this->canChangeAttachments($request->user(), $stockDelivery),
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
                    'updateRoute' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.stock-delivery.attachment.update',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                        ],
                    ],
                ],

                StockDeliveryTabsEnum::ITEMS->value => $this->tab == StockDeliveryTabsEnum::ITEMS->value ?
                    fn () => $this->getItems($stockDelivery)
                    : Inertia::optional(fn () => $this->getItems($stockDelivery)),

                StockDeliveryTabsEnum::PENDING_ITEMS->value => $this->tab == StockDeliveryTabsEnum::PENDING_ITEMS->value ?
                    fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::PENDING_ITEMS->value, stateFilter: $this->pendingItemStates()))
                    : Inertia::optional(fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::PENDING_ITEMS->value, stateFilter: $this->pendingItemStates()))),

                StockDeliveryTabsEnum::DONE_ITEMS->value => $this->tab == StockDeliveryTabsEnum::DONE_ITEMS->value ?
                    fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::DONE_ITEMS->value, stateFilter: $this->doneItemStates()))
                    : Inertia::optional(fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::DONE_ITEMS->value, stateFilter: $this->doneItemStates()))),

                StockDeliveryTabsEnum::CHECKLIST->value => $this->tab == StockDeliveryTabsEnum::CHECKLIST->value ?
                    fn () => $this->getGoodsInChecklist($stockDelivery, $request)
                    : Inertia::optional(fn () => $this->getGoodsInChecklist($stockDelivery, $request)),

                StockDeliveryTabsEnum::PACKING_CHECKS->value => $this->tab == StockDeliveryTabsEnum::PACKING_CHECKS->value ?
                    fn () => GetStockDeliveryPackingChecks::run($stockDelivery, $this->canBookIn)
                    : Inertia::optional(fn () => GetStockDeliveryPackingChecks::run($stockDelivery, $this->canBookIn)),

                StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value => $this->tab == StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value ?
                    fn () => $this->getUnderOverDeliveredItems($stockDelivery)
                    : Inertia::optional(fn () => $this->getUnderOverDeliveredItems($stockDelivery)),

                StockDeliveryTabsEnum::CUSTOMS->value => $this->tab == StockDeliveryTabsEnum::CUSTOMS->value ?
                    fn () => GetStockDeliveryCustoms::run($stockDelivery)
                    : Inertia::optional(fn () => GetStockDeliveryCustoms::run($stockDelivery)),

                StockDeliveryTabsEnum::PURCHASE_ORDERS->value => $this->tab == StockDeliveryTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($stockDelivery, StockDeliveryTabsEnum::PURCHASE_ORDERS->value))
                    : Inertia::optional(fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($stockDelivery, StockDeliveryTabsEnum::PURCHASE_ORDERS->value))),

                StockDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == StockDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))),

                StockDeliveryTabsEnum::NOTES->value => $this->tab == StockDeliveryTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($stockDelivery, StockDeliveryTabsEnum::NOTES->value, forAgent: $stockDelivery->organisation_id !== $this->organisation->id))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($stockDelivery, StockDeliveryTabsEnum::NOTES->value, forAgent: $stockDelivery->organisation_id !== $this->organisation->id))),

                'note_form' => GetAgreement::make()->noteForm($request->user(), $stockDelivery->group, $stockDelivery->organisation_id !== $this->organisation->id, $stockDelivery->organisation_id),

                'note_store_route' => [
                    'name'       => 'grp.models.stock-delivery.note.store',
                    'parameters' => [$stockDelivery->id],
                ],

                StockDeliveryTabsEnum::HISTORY->value => $this->tab == StockDeliveryTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value, auditScope: $this->historyAuditScope($stockDelivery)))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value, auditScope: $this->historyAuditScope($stockDelivery)))),
            ]
        )->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::ITEMS->value, withCosting: !$this->viewedByAgent, bookingIn: false))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::PENDING_ITEMS->value, withCosting: false))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::DONE_ITEMS->value, withCosting: false))
            ->table(IndexStockDeliveryUnderOverDeliveredItems::make()->tableStructure(prefix: StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))
            ->table(IndexPurchaseOrders::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::PURCHASE_ORDERS->value, withRemoveFromContainer: $this->canRemovePurchaseOrders))
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
                    'assignable_users' => ReopenStockDeliveryCosting::assignableUsers($stockDelivery)
                        ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->contact_name ?: $user->username])
                        ->values()->all(),
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

    /**
     * Agents never see the history of what the receiving organisation pays locally.
     */
    private function historyAuditScope(StockDelivery $stockDelivery): array
    {
        $deliveryAuditScope = [
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

        if ($this->viewedByAgent) {
            return $deliveryAuditScope;
        }

        return array_merge($deliveryAuditScope, [
            [
                'type'   => 'StockDeliveryCost',
                'labels' => $stockDelivery->costs()->withTrashed()->get()
                    ->mapWithKeys(fn (StockDeliveryCost $cost) => [$cost->id => $cost->label ?: ($cost->category ? StockDeliveryServiceInvoiceTypeEnum::labels()[$cost->category->value] : StockDeliveryCostTypeEnum::labels()[$cost->type->value])])
                    ->all(),
                'shops'  => null,
            ],
            [
                'type'   => 'StockDeliveryServiceInvoice',
                'labels' => $stockDelivery->serviceInvoices()->withTrashed()->get()
                    ->mapWithKeys(fn (StockDeliveryServiceInvoice $serviceInvoice) => [$serviceInvoice->id => trim($serviceInvoice->issuer.' '.$serviceInvoice->reference)])
                    ->all(),
                'shops'  => null,
            ],
        ]);
    }

    public function getActions(StockDelivery $stockDelivery): array
    {
        $isContainer = DeleteStockDelivery::isAgentDelivery($stockDelivery);

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
                    'filename'      => PdfStockDelivery::make()->filename($stockDelivery),
                ],
            ],
        ];

        $csvButton = [
            'type'    => 'button',
            'style'   => 'tertiary',
            'label'   => 'CSV',
            'tooltip' => __('Download the lines of this delivery'),
            'target'  => '_blank',
            'icon'    => 'fal fa-file-csv',
            'key'     => 'csv',
            'route'   => [
                'name'       => 'grp.org.procurement.stock_deliveries.csv',
                'parameters' => [
                    'organisation'  => (request()->route('organisation') ?? $stockDelivery->organisation)->slug,
                    'stockDelivery' => $stockDelivery->slug,
                ],
            ],
        ];

        $cancelButton = [
            'label'   => __('Cancel'),
            'tooltip' => match (true) {
                $this->viewedByAgent => __('Cancel shipment'),
                $isContainer         => __('Cancel Container'),
                default              => __('Cancel Stock Delivery'),
            },
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

        $deliveryDateButton = [
            'label'                    => __('Delivery date'),
            'tooltip'                  => __('Change the estimated delivery date'),
            'type'                     => 'button',
            'style'                    => 'tertiary',
            'icon'                     => 'fal fa-calendar-alt',
            'key'                      => 'edit_estimated_delivery_date',
            'estimated_receiving_date' => Arr::get($stockDelivery->data, 'estimated_receiving_date'),
            'route'                    => [
                'method'     => 'patch',
                'name'       => 'grp.models.stock-delivery.update',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
        ];

        $deleteButton = [
            'label'   => __('Delete'),
            'tooltip' => match (true) {
                $this->viewedByAgent => __('Delete shipment'),
                $isContainer         => __('Delete Container'),
                default              => __('Delete Stock Delivery'),
            },
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
        ];

        $actions = match ($stockDelivery->state) {
            StockDeliveryStateEnum::IN_PROCESS,
            StockDeliveryStateEnum::CONFIRMED,
            StockDeliveryStateEnum::READY_TO_SHIP => [
                [
                    'label'   => __('Mark as Dispatched'),
                    'tooltip' => $isContainer ? __('Mark Container as Dispatched') : __('Mark Stock Delivery as Dispatched'),
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
                    'tooltip' => $isContainer ? __('Mark Container as Received') : __('Mark Stock Delivery as Received'),
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
                $deleteButton,
                $deliveryDateButton,
            ],
            StockDeliveryStateEnum::DISPATCHED => [
                [
                    'label'   => __('Mark as Received'),
                    'tooltip' => $isContainer ? __('Mark Container as Received') : __('Mark Stock Delivery as Received'),
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
                    'tooltip' => $isContainer ? __('Revert Container to its previous state') : __('Revert Stock Delivery to its previous state'),
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
                $deliveryDateButton,
            ],
            StockDeliveryStateEnum::RECEIVED => [
                [
                    'label'   => __('Unmark as Received'),
                    'tooltip' => $isContainer ? __('Revert Container to its previous state') : __('Revert Stock Delivery to its previous state'),
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
                    'tooltip' => $isContainer ? __('Place this container, this is its final state') : __('Place this stock delivery, this is its final state'),
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

        if (DeleteStockDelivery::isAgentDelivery($stockDelivery)) {
            $actions = array_values(array_filter($actions, fn (array $action) => $action['key'] !== 'delete_stock_delivery'));
            if (!DeleteStockDelivery::agentDeliveryRefusal($stockDelivery)) {
                $actions[] = [
                    ...$deleteButton,
                    'responsibility' => [
                        'phrase'            => DeleteStockDelivery::needsResponsibility($stockDelivery) ? DeleteStockDelivery::RESPONSIBILITY_PHRASE : null,
                        'min_reason_length' => DeleteStockDelivery::MIN_REASON_LENGTH,
                        'warnings'          => [
                            __('This deletes the container :reference with all its items. Its purchase orders go back to waiting for a container.', ['reference' => $stockDelivery->reference]),
                            $this->viewedByAgent
                                ? __('Delete it only if it was made by mistake.')
                                : __('Containers are made by the agent now. Delete one only if it was made by mistake, and tell the agent.'),
                        ],
                    ],
                ];
            }
        }

        if ($stockDelivery->agent_id && !$this->viewedByAgent && !DispatchStockDelivery::buyersManageAgentDeliveries($stockDelivery)) {
            $actions = array_values(array_filter($actions, fn (array $action) => $action['key'] !== 'dispatch_stock_delivery'));
        }

        if ($stockDelivery->isManagedByPartner()) {
            $actions = $stockDelivery->state === StockDeliveryStateEnum::DISPATCHED
                ? array_values(array_filter($actions, fn (array $action) => $action['key'] === 'receive_stock_delivery'))
                : [];
        }

        return array_merge($actions, [$csvButton, $pdfButton]);
    }

    /**
     * Delete first, then Settings, the CSV and PDF downloads as one group, and dispatching the container last.
     */
    private function arrangePageHeadActions(array $actions): array
    {
        $actions   = collect($actions)->filter()->values();
        $isOfKey   = fn (string ...$keys) => fn (array $action) => in_array(Arr::get($action, 'key'), $keys, true);
        $documents = $actions->filter($isOfKey('csv', 'action'))->values()->all();

        return [
            ...$actions->filter($isOfKey('delete_stock_delivery'))->all(),
            ...$actions->filter($isOfKey('settings'))->all(),
            ...$actions->reject($isOfKey('delete_stock_delivery', 'settings', 'csv', 'action', 'dispatch_stock_delivery'))->all(),
            ...($documents ? [['type' => 'buttonGroup', 'key' => 'documents', 'button' => $documents]] : []),
            ...$actions->filter($isOfKey('dispatch_stock_delivery'))->all(),
        ];
    }

    private function getAllowedActions(StockDelivery $stockDelivery): array
    {
        if ($this->viewedByAgent) {
            $allowedKeys = $this->canEdit ? ['dispatch_stock_delivery', 'edit_estimated_delivery_date', 'csv', 'action'] : ['csv', 'action'];
            if ($this->canEdit && $stockDelivery->state === StockDeliveryStateEnum::IN_PROCESS) {
                $allowedKeys[] = 'delete_stock_delivery';
            }
        } elseif ($this->canEdit) {
            return $this->getActions($stockDelivery);
        } elseif (!$this->canBookIn || $stockDelivery->organisation_id !== $this->organisation->id) {
            return [];
        } else {
            $allowedKeys = $this->canUnreceive
                ? ['receive_stock_delivery', 'unreceive_stock_delivery', 'csv', 'action']
                : ['receive_stock_delivery', 'csv', 'action'];
        }

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
        } elseif ($stockDelivery->parent_name) {
            $orderer = [
                'type' => $stockDelivery->parent_type,
                'code' => $stockDelivery->parent_code,
                'name' => $stockDelivery->parent_name,
            ];
        }

        $weightAndVolume = $this->getStockDeliveryWeightAndVolume($stockDelivery);
        $containerSize   = Arr::get($stockDelivery->data, 'container_size');

        return [
            'first_block'  => [
                'orderer'  => $orderer,
                'delivery' => [
                    'type'             => Arr::get($stockDelivery->data, 'delivery_type'),
                    'type_label'       => StockDeliveryTypeEnum::labels()[Arr::get($stockDelivery->data, 'delivery_type')] ?? null,
                    'container_size'   => $containerSize ? Arr::get(OrgAgentOrderContainerEnum::labels(), $containerSize) : null,
                    'incoterm'         => Arr::get($stockDelivery->data, 'incoterm'),
                    'port_of_export'   => Arr::get($stockDelivery->data, 'port_of_export'),
                    'port_of_import'   => Arr::get($stockDelivery->data, 'port_of_import'),
                    'delivery_address' => Arr::get($stockDelivery->data, 'delivery_address'),
                    'shipper_reference' => Arr::get($stockDelivery->data, 'shipper_reference'),
                    'tracking_number'   => Arr::get($stockDelivery->data, 'tracking_number'),
                    'container_number'  => Arr::get($stockDelivery->data, 'container_number'),
                    'shipping_agent'    => Arr::get($stockDelivery->data, 'shipping_agent'),
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
                'total_possible_unit_mismatch_items' => $stockDelivery->number_stock_delivery_items_possible_unit_mismatch,
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
                'extra'        => $this->viewedByAgent ? null : $stockDelivery->cost_extra,
                'shipping'     => $this->viewedByAgent ? null : $stockDelivery->cost_shipping,
                'duties'       => $this->viewedByAgent ? null : $stockDelivery->cost_duties,
                'tax'          => $this->viewedByAgent ? null : $stockDelivery->cost_tax,
                'total'        => $this->viewedByAgent ? null : $stockDelivery->cost_total,
                'org_items'    => $stockDelivery->items()->sum('org_net_amount'),
                'remove_unbacked_costs_route' => $this->canEdit && !$this->viewedByAgent && RemoveStockDeliveryUnbackedCosts::hasUnbackedCosts($stockDelivery) ? [
                    'name'       => 'grp.models.stock-delivery.unbacked-costs.remove',
                    'parameters' => [$stockDelivery->id],
                ] : null,
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
                'methods'             => PurchaseOrderPaymentMethodEnum::options(),
                'payments'            => $stockDelivery->agentPayments()->with('bankStatement:id,ulid')->orderBy('date')->get()->map(fn (AgentPayment $payment) => [
                    'id'             => $payment->id,
                    'date'           => $payment->date->toDateString(),
                    'amount'         => (float) $payment->amount,
                    'reference'      => $payment->reference,
                    'notes'          => $payment->notes,
                    'method'         => $payment->method?->labels()[$payment->method->value],
                    'bank_statement' => $payment->bankStatement?->ulid,
                    'delete_route'   => ['name' => 'grp.models.agent_payment.delete', 'parameters' => ['agentPayment' => $payment->id]],
                ])->values()->all(),
                'payment_store_route' => ['name' => 'grp.models.stock-delivery.agent_payment.store', 'parameters' => ['stockDelivery' => $stockDelivery->id]],
            ] : null,
            'supplier'     => !$isAgentInvoice && !$stockDelivery->agent_id && $stockDelivery->parent_type === 'OrgSupplier' ? $this->getSupplierPayments($stockDelivery, $invoice) : null,
        ];
    }

    /**
     * A purchase order's deposit is taken off the invoice of its first delivery only, so an order that arrives
     * in several deliveries does not have it counted twice. What counts is what was paid of it, instalment by
     * instalment; an order paid before instalments were recorded counts its whole deposit.
     *
     * @return array<string, mixed>
     */
    private function getSupplierPayments(StockDelivery $stockDelivery, SupplierInvoice $invoice): array
    {
        $deposits = $stockDelivery->purchaseOrders()
            ->where(fn ($query) => $query->whereHas('depositPayments')->orWhere(fn ($query) => $query->whereNotNull('deposit_paid_at')->where('deposit_amount', '>', 0)))
            ->where('purchase_orders.currency_id', $invoice->currency_id)
            ->with('depositPayments')
            ->get()
            ->filter(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->stockDeliveries()->min('stock_deliveries.id') === $stockDelivery->id)
            ->map(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->depositPayments->isNotEmpty()
                ? [
                    'reference' => $purchaseOrder->reference,
                    'date'      => $purchaseOrder->depositPayments->max('date')->toDateString(),
                    'amount'    => round((float) $purchaseOrder->depositPayments->sum('amount'), 2),
                ]
                : [
                    'reference' => $purchaseOrder->reference,
                    'date'      => $purchaseOrder->deposit_paid_at->toDateString(),
                    'amount'    => (float) $purchaseOrder->deposit_amount,
                ])->values();

        $payments = $stockDelivery->supplierPayments()->with('bankStatement:id,ulid')->orderBy('date')->get();
        $paid     = round((float) $deposits->sum('amount') + (float) $payments->sum('amount'), 2);

        return [
            'can_edit'            => $this->canEditPayments,
            'methods'             => PurchaseOrderPaymentMethodEnum::options(),
            'deposits'            => $deposits->all(),
            'payments'            => $payments->map(fn (SupplierPayment $payment) => [
                'id'             => $payment->id,
                'date'           => $payment->date->toDateString(),
                'amount'         => (float) $payment->amount,
                'method'         => $payment->method->labels()[$payment->method->value],
                'reference'      => $payment->reference,
                'bank_statement' => $payment->bankStatement?->ulid,
                'delete_route'   => ['name' => 'grp.models.supplier_payment.delete', 'parameters' => ['supplierPayment' => $payment->id]],
            ])->values()->all(),
            'paid'                => $paid,
            'balance_due'         => round((float) $invoice->total_amount - $paid, 2),
            'payment_store_route' => ['name' => 'grp.models.stock-delivery.supplier_payment.store', 'parameters' => ['stockDelivery' => $stockDelivery->id]],
        ];
    }

    public function getTimeline(StockDelivery $stockDelivery, bool $withPurchaseOrderStates = true): array
    {
        $purchaseOrders = $withPurchaseOrderStates ? $stockDelivery->purchaseOrders()->get() : new EloquentCollection();

        $timeline = $purchaseOrders->isNotEmpty() ? $this->getPurchaseOrderTimeline($purchaseOrders) : [];

        $labels      = $this->getStateLabels($stockDelivery);
        $creatorName = $this->getCreatorName($stockDelivery);

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
                'description'       => $case === StockDeliveryStateEnum::IN_PROCESS && $creatorName ? __('by :name', ['name' => $creatorName]) : null,
            ];
        }

        return $timeline;
    }

    private function getCreatorName(StockDelivery $stockDelivery): ?string
    {
        $creator = $stockDelivery->audits()->where('event', 'created')->oldest()->first()?->user;

        return $creator ? ($creator->contact_name ?: $creator->username) : null;
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
                            'label' => match (true) {
                                request()->route('organisation') instanceof Organisation && $stockDelivery->agent?->organisation_id === request()->route('organisation')->id => __('Shipments'),
                                DeleteStockDelivery::isAgentDelivery($stockDelivery) => __('Containers'),
                                default => __('Supplier delivery'),
                            },
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

    private function getGoodsInBreadcrumbs(StockDelivery $stockDelivery, array $routeParameters): array
    {
        $warehouseParameters = Arr::only($routeParameters, ['organisation', 'warehouse']);

        return array_merge(
            ShowIncomingHub::make()->getBreadcrumbs($warehouseParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Stock deliveries'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                                'parameters' => $warehouseParameters,
                            ],
                        ],
                        'model' => [
                            'label' => $stockDelivery->reference,
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.show',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
            ],
        );
    }

    /**
     * Booking in is done on the warehouse's page of the delivery; procurement gets the way there.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBookInLinkAction(StockDelivery $stockDelivery, ActionRequest $request): array
    {
        $bookingInStates = [
            StockDeliveryStateEnum::RECEIVED,
            StockDeliveryStateEnum::CHECKED,
            StockDeliveryStateEnum::BOOKING_IN,
            StockDeliveryStateEnum::BOOKED_IN,
        ];

        if ($this->goodsIn || $this->viewedByAgent || !in_array($stockDelivery->state, $bookingInStates, true) || $stockDelivery->organisation_id !== $this->organisation->id) {
            return [];
        }

        $warehouse = $this->organisation->warehouses()->get()
            ->first(fn (Warehouse $warehouse) => $request->user()->authTo("incoming.$warehouse->id.view"));

        return $warehouse ? [[
            'type'  => 'button',
            'style' => 'tertiary',
            'label' => __('Book in'),
            'icon'  => 'fal fa-inventory',
            'key'   => 'book_in_link',
            'route' => [
                'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.show',
                'parameters' => [
                    'organisation'  => $this->organisation->slug,
                    'warehouse'     => $warehouse->slug,
                    'stockDelivery' => $stockDelivery->slug,
                ],
            ],
        ]] : [];
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
        if ($stockDelivery->state === StockDeliveryStateEnum::PLACED && !$this->viewedByAgent) {
            return StockDeliveryItemCostResource::collection(
                IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value, numberOfRecords: config('ui.table.max_records_per_page'))
            );
        }

        return StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value))
            ->additional(['container_lines' => SetStockDeliveryItemPackedInside::containerLines($stockDelivery)]);
    }

    /**
     * Agents never see what the receiving organisation pays locally.
     */
    private function getServiceInvoices(StockDelivery $stockDelivery): ?array
    {
        if ($this->organisation->type === OrganisationTypeEnum::AGENT || $stockDelivery->parent_type === 'OrgPartner'
            || !request()->user()?->authTo(["procurement.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.view"])) {
            return null;
        }

        $serviceInvoices = $stockDelivery->serviceInvoices()
            ->with(['currency:id,code', 'stockDeliveries:id,slug,reference', 'attachments'])
            ->orderBy('date')
            ->get();

        return [
            'list'         => $serviceInvoices->map(fn (StockDeliveryServiceInvoice $serviceInvoice) => IndexStockDeliveryServiceInvoices::row($serviceInvoice, $stockDelivery->organisation))->all(),
            'can_edit'     => $this->canEditPayments || $this->canUpdateCosting,
            'is_costed'    => $stockDelivery->is_costed,
            'types'        => IndexStockDeliveryServiceInvoices::typeOptions(),
            'org_currency' => $stockDelivery->organisation->currency->code,
            'org_currency_id' => $stockDelivery->organisation->currency_id,
            'currencies'   => Currency::orderBy('code')->get(['id', 'code'])->toArray(),
            'stock_delivery_id' => $stockDelivery->id,
            'stock_deliveries'  => StockDelivery::where('organisation_id', $stockDelivery->organisation_id)
                ->where('is_costed', false)
                ->whereNotIn('state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'reference'])
                ->toArray(),
            'store_route'  => ['name' => 'grp.models.org.stock_delivery_service_invoice.store', 'parameters' => ['organisation' => $stockDelivery->organisation_id], 'method' => 'post'],
            'index_route'  => ['name' => 'grp.org.procurement.service_invoices.index', 'parameters' => [$stockDelivery->organisation->slug]],
            'readings'     => GetStockDeliveryServiceInvoiceReadings::run($stockDelivery),
            'hand_typed'   => GetStockDeliveryHandTypedCosts::run($stockDelivery),
            'cost_kinds'   => GetStockDeliveryHandTypedCosts::costKinds(),
            'read_route'   => ['name' => 'grp.models.stock-delivery.service-invoice.read-upload', 'parameters' => ['stockDelivery' => $stockDelivery->id], 'method' => 'post'],
        ];
    }

    private function isCostingEditable(StockDelivery $stockDelivery, bool $canEditPayments, bool $canUpdateCosting): bool
    {
        return !$stockDelivery->is_costed && (Arr::has($stockDelivery->data, 'costing_reopened') ? $canUpdateCosting : ($canEditPayments || $canUpdateCosting));
    }

    private function getCosting(StockDelivery $stockDelivery): array
    {
        $costs       = $stockDelivery->costs()->with(['attachments', 'audits.user'])->orderBy('id')->get();
        $invoices    = $this->getCostInvoices($stockDelivery);
        $uploadRoute = fn (?StockDeliveryCost $row) => ($this->canEditPayments || $this->canUpdateCosting) && !$row?->from_service_invoices ? [
            'name'       => 'grp.models.stock-delivery.cost.invoice.attach',
            'parameters' => array_filter(['stockDelivery' => $stockDelivery->id, 'cost' => $row?->id]),
            'method'     => 'post',
        ] : null;

        $checklist = [];
        foreach ($this->viewedByAgent ? [] : [StockDeliveryCostTypeEnum::AGENT_INVOICE, StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY] as $type) {
            $row         = $costs->firstWhere('type', $type);
            $checklist[] = $this->costRow($type, $row, $invoices, $uploadRoute($row));
        }
        foreach ($this->viewedByAgent ? [] : $costs->where('type', StockDeliveryCostTypeEnum::EXTRA) as $row) {
            $checklist[] = $this->costRow(StockDeliveryCostTypeEnum::EXTRA, $row, $invoices, $uploadRoute($row));
        }

        $agentInvoice = $costs->firstWhere('type', StockDeliveryCostTypeEnum::AGENT_INVOICE);

        $applications  = $stockDelivery->depositApplications()->orderBy('id')->get();
        $depositsTotal = (float) $applications->sum('amount');
        $agentInvoiceAmount = (float) ($agentInvoice?->amount ?? 0);

        return [
            'is_costed'                  => $stockDelivery->is_costed,
            'is_partner'                 => $stockDelivery->parent_type === 'OrgPartner',
            'viewed_by_agent'            => $this->viewedByAgent,
            'can_edit'                   => $this->isCostingEditable($stockDelivery, $this->canEditPayments, $this->canUpdateCosting),
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
            'extra_types'                => StockDeliveryServiceInvoiceTypeEnum::extraOptions(),
            'costs_missing'              => $this->viewedByAgent ? [] : $stockDelivery->costsMissing(),
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
            'assignee' => User::find(Arr::get($reopened, 'assignee_id'))?->contact_name,
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

    /**
     * The invoice files behind the checklist rows that keep none of their own: the agent or supplier invoice
     * filed on the delivery, and the files of the service invoices that fill the shipping, duty and extra rows.
     *
     * @return array{agent_invoice: Collection<int, Media>, by_cost_type: Collection<string, Collection<int, Media>>, by_service_invoice: Collection<int, Collection<int, Media>>}
     */
    private function getCostInvoices(StockDelivery $stockDelivery): array
    {
        $serviceInvoices = $stockDelivery->serviceInvoices()->with('attachments')->get();

        return [
            'agent_invoice'      => $stockDelivery->attachments()
                ->wherePivotIn('scope', [PurchaseOrderAttachmentScopeEnum::INVOICE->value, PurchaseOrderAttachmentScopeEnum::PROFORMA->value])
                ->get(),
            'by_cost_type'       => $serviceInvoices
                ->groupBy(fn (StockDeliveryServiceInvoice $serviceInvoice) => $serviceInvoice->type->costType()?->value)
                ->map(fn (Collection $bills) => $bills->flatMap->attachments),
            'by_service_invoice' => $serviceInvoices->keyBy('id')->map->attachments,
        ];
    }

    /**
     * @param  array{agent_invoice: Collection<int, Media>, by_cost_type: Collection<string, Collection<int, Media>>, by_service_invoice: Collection<int, Collection<int, Media>>}  $invoices
     * @param  array{name: string, parameters: array<string, int>, method: string}|null  $uploadRoute
     */
    private function costRow(StockDeliveryCostTypeEnum $type, ?StockDeliveryCost $row, array $invoices, ?array $uploadRoute): array
    {
        $files = match (true) {
            $type === StockDeliveryCostTypeEnum::AGENT_INVOICE  => $invoices['agent_invoice'],
            (bool) $row?->stock_delivery_service_invoice_id     => $invoices['by_service_invoice']->get($row->stock_delivery_service_invoice_id, collect()),
            (bool) $row?->from_service_invoices                 => $invoices['by_cost_type']->get($type->value, collect()),
            default                                             => collect(),
        };

        $category  = $row?->category ?? ($type === StockDeliveryCostTypeEnum::EXTRA ? StockDeliveryServiceInvoiceTypeEnum::OTHER : null);
        $isInvoice = $type === StockDeliveryCostTypeEnum::AGENT_INVOICE;
        $kindLabel = $category ? StockDeliveryServiceInvoiceTypeEnum::labels()[$category->value] : StockDeliveryCostTypeEnum::labels()[$type->value];

        return [
            'from_service_invoices' => (bool) $row?->from_service_invoices,
            'is_waiting_for_invoice' => (bool) $row?->is_waiting_for_invoice,
            'is_estimate'            => (bool) $row?->is_estimate,
            'org_amount'             => $row && !$row->is_na && $row->amount !== null
                ? round($row->amountInDeliveryCurrency() * (float) ($row->stockDelivery->org_exchange ?: 1), 2)
                : null,
            'invoice_overdue'        => (bool) ($row?->is_waiting_for_invoice && $row->invoice_expected_at?->isBefore(today())),
            'waiting_label'          => $row?->waitingLabel(),
            'attachments'           => collect($row?->attachments)->concat($files)->unique('id')
                ->map(fn (Media $media) => ['name' => $media->name ?: $media->file_name, 'ulid' => $media->ulid])->values()->all(),
            'uploadRoute'           => $uploadRoute,
            'id'          => $row?->id,
            'type'        => $type->value,
            'category'       => $isInvoice ? null : ($category?->value ?? $type->value),
            'category_label' => $isInvoice ? null : $kindLabel,
            'note'           => $isInvoice ? null : $row?->label,
            'history'        => $row ? $this->costHistory($row) : [],
            'label'       => $isInvoice ? ($row?->label ?: $kindLabel) : $kindLabel.($category && $row?->label ? ': '.$row->label : ''),
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

    /**
     * Who changed what on a cost row, newest first.
     *
     * @return array<int, array{at: string, user: string, event: string, changes: array<int, array{field: string, old: string, new: string}>}>
     */
    private function costHistory(StockDeliveryCost $cost): array
    {
        $fields = [
            'category'               => __('Kind'),
            'label'                  => __('Note'),
            'amount'                 => __('Amount'),
            'currency_id'            => __('Currency'),
            'exchange'               => __('Rate'),
            'is_na'                  => __('N/A'),
            'received_at'            => __('Received'),
            'is_waiting_for_invoice' => __('Waiting for invoice'),
            'invoice_expected_at'    => __('Invoice expected'),
            'is_estimate'            => __('Estimate'),
        ];

        $show = fn (string $field, mixed $value): string => match (true) {
            $value === null || $value === ''                                 => '-',
            in_array($field, ['is_na', 'is_waiting_for_invoice', 'is_estimate'])            => $value ? __('Yes') : __('No'),
            $field === 'currency_id'                                         => Currency::find($value)?->code ?? (string) $value,
            $field === 'category'                                            => StockDeliveryServiceInvoiceTypeEnum::labels()[$value] ?? (string) $value,
            in_array($field, ['received_at', 'invoice_expected_at'])         => Carbon::parse($value)->toDateString(),
            default                                                          => (string) $value,
        };

        return $cost->audits->sortByDesc('id')->map(fn (Audit $audit) => [
            'at'      => $audit->created_at->toIso8601String(),
            'user'    => $audit->user?->contact_name ?? __('System'),
            'event'   => $audit->event,
            'changes' => collect($fields)
                ->filter(fn (string $label, string $field) => array_key_exists($field, $audit->new_values ?? []) || array_key_exists($field, $audit->old_values ?? []))
                ->map(fn (string $label, string $field) => [
                    'field' => $label,
                    'old'   => $show($field, Arr::get($audit->old_values, $field)),
                    'new'   => $show($field, Arr::get($audit->new_values, $field)),
                ])
                ->filter(fn (array $change) => $change['old'] !== $change['new'])
                ->values()->all(),
        ])->filter(fn (array $entry) => $entry['changes'] !== [])->values()->all();
    }

    private function getAddPurchaseOrders(StockDelivery $stockDelivery): array
    {
        $purchaseOrderIdsInContainer = $stockDelivery->purchaseOrders()->pluck('purchase_orders.id')->all();

        return [
            'route'           => [
                'method'     => 'post',
                'name'       => 'grp.models.stock-delivery.purchase_orders.add',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
            'purchase_orders' => AddPurchaseOrdersToStockDelivery::addablePurchaseOrders($stockDelivery)
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder) => [
                    'id'                             => $purchaseOrder->id,
                    'reference'                      => $purchaseOrder->reference,
                    'supplier'                       => $purchaseOrder->parent_name,
                    'confirmed_at'                   => $purchaseOrder->confirmed_at,
                    'number_items_awaiting_delivery' => $purchaseOrder->number_items_awaiting_delivery,
                    'in_this_container'              => in_array($purchaseOrder->id, $purchaseOrderIdsInContainer, true),
                ])
                ->values(),
        ];
    }

    /**
     * Every line still awaiting delivery on the purchase orders this container accepts, in units, with what one SKO
     * and one carton hold so the page can show the quantity each way.
     */
    private function getAddPurchaseOrderItems(StockDelivery $stockDelivery): array
    {
        return [
            'route'                => [
                'method'     => 'post',
                'name'       => 'grp.models.stock-delivery.purchase_order_items.add',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
            'from_agent_warehouse' => AddPurchaseOrdersToStockDelivery::shipsFromAgentWarehouse($stockDelivery),
            'items'                => AddPurchaseOrderItemsToStockDelivery::addableItems($stockDelivery)
                ->with([
                    'purchaseOrder:id,reference,parent_name',
                    'supplierProduct:id,code,name,units_per_pack,units_per_carton',
                    'orgStock:id,code,name,packed_in',
                ])
                ->orderBy('purchase_order_transactions.purchase_order_id')
                ->orderBy('purchase_order_transactions.id')
                ->get()
                ->map(fn (PurchaseOrderTransaction $transaction) => [
                    'id'                         => $transaction->id,
                    'purchase_order_id'          => $transaction->purchase_order_id,
                    'purchase_order_reference'   => $transaction->purchaseOrder->reference,
                    'supplier'                   => $transaction->purchaseOrder->parent_name,
                    'code'                       => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                    'name'                       => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                    'quantity_ordered'           => (float) $transaction->quantity_ordered,
                    'quantity_awaiting_delivery' => (float) $transaction->quantity_awaiting_delivery,
                    'units_per_sko'              => (float) (($transaction->supplierProduct ? $transaction->supplierProduct->units_per_pack : $transaction->orgStock?->packed_in) ?: 1),
                    'units_per_carton'           => (float) (($transaction->supplierProduct ? $transaction->supplierProduct->units_per_carton : $transaction->orgStock?->packed_in) ?: 0),
                ])
                ->values()
                ->all(),
        ];
    }

    private function getRemovePurchaseOrders(StockDelivery $stockDelivery): array
    {
        return $stockDelivery->purchaseOrders()->get()
            ->mapWithKeys(fn (PurchaseOrder $purchaseOrder) => [
                $purchaseOrder->id => [
                    'reference' => $purchaseOrder->reference,
                    'route'     => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.stock-delivery.purchase_orders.remove',
                        'parameters' => [
                            'stockDelivery' => $stockDelivery->id,
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                    'items'     => RemovePurchaseOrderFromStockDelivery::itemsOfPurchaseOrder($stockDelivery, $purchaseOrder)
                        ->with(['supplierProduct:id,code,name', 'orgStock:id,code,name'])
                        ->orderBy('id')
                        ->get()
                        ->map(fn (StockDeliveryItem $item) => [
                            'id'            => $item->id,
                            'code'          => $item->supplierProduct?->code ?? $item->orgStock?->code,
                            'name'          => $item->supplierProduct?->name ?? $item->orgStock?->name,
                            'unit_quantity' => (float) $item->unit_quantity,
                        ])
                        ->values(),
                ],
            ])
            ->all();
    }

    /**
     * @param  string[]  $missingEvidence
     */
    private function getTabsNavigation(StockDelivery $stockDelivery, array $missingEvidence): array
    {
        $navigation = Arr::only(StockDeliveryTabsEnum::navigation(), $this->getTabs($stockDelivery));

        if (isset($navigation[StockDeliveryTabsEnum::ATTACHMENTS->value])) {
            $navigation[StockDeliveryTabsEnum::ATTACHMENTS->value]['number'] = $stockDelivery->attachments()->count();

            if ($missingEvidence) {
                $navigation[StockDeliveryTabsEnum::ATTACHMENTS->value]['indicator'] = true;
                $navigation[StockDeliveryTabsEnum::ATTACHMENTS->value]['title']     = __('Attachments (missing: :documents)', ['documents' => implode(', ', $missingEvidence)]);
            }
        }

        if (!$this->canAddPurchaseOrders && !$stockDelivery->purchaseOrders()->exists()) {
            unset($navigation[StockDeliveryTabsEnum::PURCHASE_ORDERS->value]);
        }

        if (isset($navigation[StockDeliveryTabsEnum::CHECKLIST->value])) {
            $checklist = GetStockDeliveryGoodsInChecklist::make()->forPage($stockDelivery);
            $navigation[StockDeliveryTabsEnum::CHECKLIST->value]['number'] = $checklist['total'] - $checklist['checked'];
        }

        if (!$this->goodsIn && $stockDelivery->state === StockDeliveryStateEnum::PLACED && !$this->viewedByAgent) {
            $navigation[StockDeliveryTabsEnum::ITEMS->value]['title'] = __('Items (costing)');
            $navigation[StockDeliveryTabsEnum::ITEMS->value]['icon']  = 'fal fa-box-usd';
        }

        return $navigation;
    }

    /**
     * @return array<string, mixed>
     */
    private function getGoodsInChecklist(StockDelivery $stockDelivery, ActionRequest $request): array
    {
        return GetStockDeliveryGoodsInChecklist::make()->forPage($stockDelivery) + [
            'can_check'      => $this->canBookIn && in_array($stockDelivery->state, GetStockDeliveryGoodsInChecklist::STATES, true),
            'incident_types' => ProcurementNoteIncidentTypeEnum::valuesWithLabels(),
            'route'          => [
                'name'       => 'grp.models.stock-delivery.goods_in_check.store',
                'parameters' => [$stockDelivery->id],
            ],
        ];
    }

    private function hasUnderOverDeliveredTab(StockDelivery $stockDelivery): bool
    {
        if ($this->goodsIn) {
            return in_array($stockDelivery->state, [StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN, StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED], true);
        }

        return in_array($stockDelivery->state, [StockDeliveryStateEnum::BOOKED_IN, StockDeliveryStateEnum::PLACED], true);
    }

    /**
     * Booking in sees the differences while it works; resolving them and the claims stay with procurement.
     */
    private function getUnderOverDeliveredItems(StockDelivery $stockDelivery): AnonymousResourceCollection
    {
        return StockDeliveryUnderOverDeliveredItemResource::collection(IndexStockDeliveryUnderOverDeliveredItems::run($stockDelivery, StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))
            ->additional(['read_only' => $this->goodsIn]);
    }

    private function hasCustomsTab(StockDelivery $stockDelivery): bool
    {
        return !$this->viewedByAgent
            && !in_array($stockDelivery->state, [StockDeliveryStateEnum::IN_PROCESS, StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP, StockDeliveryStateEnum::CANCELLED], true);
    }

    private function getTabs(StockDelivery $stockDelivery): array
    {
        $tabs = array_values(array_diff(
            StockDeliveryTabsEnum::values(),
            array_column($this->goodsIn ? self::PROCUREMENT_ONLY_TABS : self::BOOKING_IN_TABS, 'value')
        ));
        if (!$this->hasCustomsTab($stockDelivery)) {
            $tabs = array_values(array_diff($tabs, [StockDeliveryTabsEnum::CUSTOMS->value]));
        }

        if ($this->viewedByAgent || !in_array($stockDelivery->state, GetStockDeliveryGoodsInChecklist::STATES, true)) {
            $tabs = array_values(array_diff($tabs, [StockDeliveryTabsEnum::CHECKLIST->value]));
        }

        if ($this->viewedByAgent || !in_array($stockDelivery->state, StockDelivery::STATES_RECEIVED, true)) {
            $tabs = array_values(array_diff($tabs, [StockDeliveryTabsEnum::PACKING_CHECKS->value]));
        }

        if ($this->hasUnderOverDeliveredTab($stockDelivery)) {
            return $tabs;
        }

        return array_values(array_diff($tabs, [StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value]));
    }
}
