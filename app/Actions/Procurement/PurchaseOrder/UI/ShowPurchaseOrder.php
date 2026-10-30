<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\PurchaseOrder\PdfPackingInstructions;
use App\Actions\SupplyChain\PackingSpec\GetPurchaseOrderPackingSpecGaps;
use App\Events\BroadcastPurchaseOrderLastEdited;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderPaymentMethodEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Procurement\PurchaseOrderComplianceDocumentsResource;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Actions\OrgAction;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\UI\IndexAgentCoverBucketItems;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\ConfirmPurchaseOrderPayment;
use App\Actions\Procurement\PurchaseOrder\ResolvePurchaseOrderDeliveryAddress;
use App\Actions\Procurement\PurchaseOrder\Traits\WithPurchaseOrderWeightAndVolume;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\UI\Procurement\PurchaseOrderTabsEnum;
use App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier;
use App\Actions\Procurement\PurchaseOrder\SendPartnerPurchaseOrderToSeller;
use App\Actions\Ordering\Order\UI\IndexDispatchedEmailsInOrder;
use App\Http\Resources\Ordering\DispatchedEmailsInOrderResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\PurchaseOrderOrgSupplierProductsResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AspoDeposit;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderDepositPaid;
use App\Actions\Procurement\AgentGoodsReceipt\UI\GetPurchaseOrderGoodsReceipts;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Ordering\Order;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPurchaseOrderWeightAndVolume;
    use WithAgentOrganisation;

    private const array AGENT_ACTION_KEYS = ['edit_estimated_delivery_date', 'cancel_purchase_order'];

    private bool $agentCanEdit = false;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request)->withTab(PurchaseOrderTabsEnum::values(), $this->defaultTab($purchaseOrder));
        $this->authorizeProcurementRecord($purchaseOrder);
        $this->agentCanEdit = $this->agentEditsOwnOrder($purchaseOrder, $request->user());

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
        abort_unless(
            $purchaseOrder->agent_id !== null
            && $purchaseOrder->agent_id === $orgAgent->agent_id
            && $purchaseOrder->organisation_id === $orgAgent->organisation_id,
            404
        );
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
            && $purchaseOrder->parent instanceof OrgSupplier
            && !$purchaseOrder->purchaseOrderTransactions()->exists();

        return $isEmptyOpenOrder ? PurchaseOrderTabsEnum::PRODUCTS->value : PurchaseOrderTabsEnum::ITEMS->value;
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->validateAttributes();

        $showProductsTab = $purchaseOrder->state == PurchaseOrderStateEnum::IN_PROCESS
            && $purchaseOrder->parent instanceof OrgSupplier;
        $uploadExcel = $this->canEdit && $this->acceptsSpreadsheet($purchaseOrder);
        $draftOrgAgent = $this->draftOrgAgent($purchaseOrder);
        $products = fn () => match (true) {
            !$showProductsTab        => null,
            $draftOrgAgent !== null => JsonResource::collection(IndexAgentCoverBucketItems::make()->onSupplier($draftOrgAgent, $purchaseOrder->supplier_id, PurchaseOrderTabsEnum::PRODUCTS->value))
                ->additional(['ordering' => IndexAgentCoverBucketItems::make()->orderingFilters($draftOrgAgent, $purchaseOrder->supplier_id, $purchaseOrder->organisation->currency->code, PurchaseOrderTabsEnum::PRODUCTS->value)]),
            default                  => PurchaseOrderOrgSupplierProductsResource::collection(IndexPurchaseOrderOrgSupplierProducts::run($purchaseOrder->parent, $purchaseOrder, PurchaseOrderTabsEnum::PRODUCTS->value))
                ->additional(['ordering' => IndexPurchaseOrderOrgSupplierProducts::make()->orderingFilters($purchaseOrder->parent, $purchaseOrder, PurchaseOrderTabsEnum::PRODUCTS->value)]),
        };
        $items = fn () => $draftOrgAgent
            ? JsonResource::collection(IndexAgentCoverBucketItems::make()->onSupplierDrafts($draftOrgAgent, $purchaseOrder->supplier_id, PurchaseOrderTabsEnum::ITEMS->value))
            : PurchaseOrderTransactionResource::collection(IndexPurchaseOrderTransactions::run($purchaseOrder, PurchaseOrderTabsEnum::ITEMS->value));

        $orderer = [];
        $productListRoute = [];
        $weightAndVolume = $this->getPurchaseOrderWeightAndVolume($purchaseOrder);
        $deliveryStats = $this->getDeliveryStats($purchaseOrder);
        $hasItems = $purchaseOrder->number_purchase_order_transactions > 0;
        $deliveryAddress = ResolvePurchaseOrderDeliveryAddress::run(
            $purchaseOrder->organisation,
            Arr::get($purchaseOrder->data, 'delivery_address')
        );

        if ($purchaseOrder->parent instanceof OrgPartner) {
            $orderer = ['name' => $purchaseOrder->parent->partner->name, 'type' => 'Partner'];
            $productListRoute = [
                'method'     => 'get',
                'name'       => 'grp.json.org-partner.purchase-order-org-stocks',
                'parameters' => [
                    'orgPartner'    => $purchaseOrder->parent->id,
                    'purchaseOrder' => $purchaseOrder->slug,
                ],
            ];
        } elseif ($purchaseOrder->parent instanceof OrgSupplier) {
            $orderer = OrgSupplierResource::make($purchaseOrder->parent)->toArray($request);
            if ($purchaseOrder->isAgentOrder() && $orgAgent = $purchaseOrder->orgAgentOfOrder()) {
                $orderer['via_agent'] = [
                    'name' => $orgAgent->agent->name,
                    'slug' => $orgAgent->slug,
                ];
            }
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
                'title'       => '(' . $purchaseOrder->reference . ') ' . __('Purchase Order'),
                'breadcrumbs' => $this->getBreadcrumbs($purchaseOrder, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($purchaseOrder, $request),
                    'next'     => $this->getNext($purchaseOrder, $request),
                ],
                'pageHead'    => [
                    'title' => $purchaseOrder->reference,
                    'model' => __('Purchase Order'),
                    'icon'  => [
                        'icon'  => ['fal', 'clipboard-list'],
                        'title' => __('Purchase Order'),
                    ],
                    'afterTitle' => [
                        'label' => $purchaseOrder->state->labels()[$purchaseOrder->state->value],
                    ],
                    'actions' => $this->arrangePageHeadActions([
                        $this->canEdit || $this->agentCanEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'key'   => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.edit',
                                'parameters' => [$this->organisation->slug, $purchaseOrder->slug],
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
                                'parameters' => ['organisation' => $this->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug, 'filename' => PdfPurchaseOrder::make()->filename($purchaseOrder)],
                            ],
                            'options' => [
                                [
                                    'value'      => 'with_images',
                                    'label'      => __('Product images'),
                                    'is_checked' => false,
                                ],
                            ],
                        ],
                        in_array($purchaseOrder->parent_type, ['OrgAgent', 'OrgSupplier']) ? [
                            'type'    => 'button',
                            'style'   => 'tertiary',
                            'label'   => __('Packing instructions'),
                            'tooltip' => __('One page per product for the packers. Print a single product from the box icon next to its code.'),
                            'target'  => '_blank',
                            'icon'    => 'fal fa-box-open',
                            'key'     => 'packing_instructions',
                            'route'   => [
                                'name'       => 'grp.org.procurement.purchase_orders.packing_instructions',
                                'parameters' => ['organisation' => $this->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug, 'filename' => PdfPackingInstructions::make()->filename($purchaseOrder)],
                            ],
                        ] : false,
                        in_array($purchaseOrder->parent_type, ['OrgAgent', 'OrgSupplier']) ? [
                            'type'         => 'button',
                            'style'        => 'tertiary',
                            'label'        => __('Labels'),
                            'tooltip'      => __('One PDF with a label per carton, SKO or unit of every line, for the supplier. The link works for 7 days without an account, so it can go in the email.'),
                            'target'       => '_blank',
                            'icon'         => 'fal fa-tags',
                            'key'          => 'labels',
                            'optionsTitle' => __('Labels to include'),
                            'route'        => [
                                'name'       => 'grp.org.procurement.purchase_orders.labels',
                                'parameters' => ['organisation' => $this->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug],
                            ],
                            'options'      => [
                                ['value' => 'carton', 'label' => __('Carton labels'), 'is_checked' => true],
                                ['value' => 'sko', 'label' => __('SKO labels'), 'is_checked' => false],
                                ['value' => 'unit', 'label' => __('Unit labels'), 'is_checked' => false],
                                ['value' => 'unique', 'label' => __('Unique only (one per product)'), 'is_checked' => false, 'separated' => true],
                            ],
                        ] : false,
                        ...($uploadExcel ? [] : [$this->downloadExcelAction($purchaseOrder)]),
                        ...($this->emailToSupplierAction($purchaseOrder) ?? []),
                        ...match (true) {
                            $this->agentCanEdit => $this->getAgentActions($purchaseOrder),
                            $this->canEdit      => $this->getActions($purchaseOrder, $showProductsTab),
                            default             => [],
                        },
                    ]),
                ],
                'upload_excel'             => $uploadExcel ? $this->uploadExcel($purchaseOrder) : null,
                'submit_problems'          => Inertia::optional(fn () => $purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS && SendPartnerPurchaseOrderToSeller::appliesTo($purchaseOrder)
                    ? SendPartnerPurchaseOrderToSeller::make()->submitProblems($purchaseOrder, $request->user())
                    : []),
                'data'                     => PurchaseOrderResource::make($purchaseOrder),
                'last_edit'                => BroadcastPurchaseOrderLastEdited::lastEdit($purchaseOrder),
                'timelines'                => $this->getTimeline($purchaseOrder),
                'stock_delivery_timelines' => $this->getStockDeliveryTimelines($purchaseOrder),
                'stages'                   => $this->getStages($purchaseOrder),
                'agent_receiving'          => fn () => $this->isAgentViewingOwnOrder($purchaseOrder) ? GetPurchaseOrderGoodsReceipts::run($purchaseOrder, $this->agentCanEdit) : null,
                'open_agent_deliveries'    => $purchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED
                    ? StoreStockDeliveryFromPurchaseOrder::openAgentDeliveries($purchaseOrder)
                        ->map(fn (StockDelivery $stockDelivery) => [
                            'id'                     => $stockDelivery->id,
                            'reference'              => $stockDelivery->reference,
                            'date'                   => $stockDelivery->date,
                            'number_purchase_orders' => $stockDelivery->number_purchase_orders,
                        ])->values()
                    : [],
                'deposits'                 => $purchaseOrder->isAgentOrder() ? $this->getDeposits($purchaseOrder) : null,
                'agent_money'              => $agentMoney = ConfirmPurchaseOrderPayment::acceptsPayments($purchaseOrder) ? $this->getAgentMoney($purchaseOrder) : null,
                'payments'                 => !$agentMoney && ConfirmPurchaseOrderPayment::acceptsPayments($purchaseOrder) ? $this->getPayments($purchaseOrder, $request) : null,
                'delivery_items'            => $purchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED
                    ? $purchaseOrder->purchaseOrderTransactions()
                        ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
                        ->with(['supplierProduct:id,code,name', 'orgStock:id,code,name', 'orgStock.tradeUnits.image'])
                        ->get(['id', 'supplier_product_id', 'org_stock_id', 'quantity_ordered'])
                        ->map(function (PurchaseOrderTransaction $transaction) {
                            $tradeUnit = $transaction->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

                            return [
                                'id'               => $transaction->id,
                                'code'             => $transaction->supplierProduct?->code ?? $transaction->orgStock?->code,
                                'name'             => $transaction->supplierProduct?->name ?? $transaction->orgStock?->name,
                                'quantity_ordered' => $transaction->quantity_ordered,
                                'image_thumbnail'  => $tradeUnit?->imageSources(80, 80),
                                'image_full'       => $tradeUnit?->image?->fullResolutionUrls(),
                            ];
                        })->values()
                    : [],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PurchaseOrderTabsEnum::navigationExcept(array_filter([
                        $showProductsTab ? null : PurchaseOrderTabsEnum::PRODUCTS,
                        $purchaseOrder->parent_type === 'OrgPartner' ? PurchaseOrderTabsEnum::COMPLIANCE : null,
                    ])),
                ],
                'routes'      => [
                    'updatePurchaseOrderRoute' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => [
                            'purchaseOrder' => $purchaseOrder->id,
                        ],
                    ],
                    'products_list' => $purchaseOrder->organisation_id === $this->organisation->id ? $productListRoute : [],
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
                            'is_own_warehouse' => $purchaseOrder->organisation_id === $this->organisation->id
                                && $deliveryAddress === ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->organisation),
                        ],
                        'seller_order' => $this->sellerOrder($purchaseOrder, $request),
                        'clean_handover' => $purchaseOrder->isAgentOrder() ? [
                            'proposed_ready_at'      => $purchaseOrder->proposed_ready_at,
                            'approved_ready_at'      => $purchaseOrder->approved_ready_at,
                            'compliance_complete_at' => $purchaseOrder->compliance_complete_at,
                            'chs_excluded'           => $purchaseOrder->chs_excluded,
                            'chs_exclusion_reason'   => $purchaseOrder->chs_exclusion_reason,
                        ] : null,
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
                        'estimated_expenses' => $this->estimatedExpenses($purchaseOrder),
                    ],
                ],

                PurchaseOrderTabsEnum::ITEMS->value => $this->tab == PurchaseOrderTabsEnum::ITEMS->value ? $items : Inertia::optional($items),

                'agent_ordering' => $draftOrgAgent ? [
                    'orgAgent'     => [
                        'id'       => $draftOrgAgent->id,
                        'slug'     => $draftOrgAgent->slug,
                        'currency' => $purchaseOrder->organisation->currency->code,
                    ],
                    'addRoute'     => [
                        'name'       => 'grp.org.procurement.org_agents.show.shopping.drafts.line',
                        'parameters' => [$purchaseOrder->organisation->slug, $draftOrgAgent->slug],
                    ],
                    'bucketLabels' => collect(GetAgentStockCoverBuckets::BUCKETS)->map(fn (array $meta) => ['label' => __($meta['label']), 'tone' => $meta['tone']])->all(),
                ] : null,

                PurchaseOrderTabsEnum::PRODUCTS->value => $showProductsTab && $this->tab == PurchaseOrderTabsEnum::PRODUCTS->value ? $products : Inertia::optional($products),

                'showcase' => GetPurchaseOrderData::run($purchaseOrder),

                'agreement' => GetPurchaseOrderAgreement::run($purchaseOrder, $purchaseOrder->organisation_id !== $this->organisation->id),
                'productDevelopments' => (new ProductDevelopmentAccess())->canSeeAny($request->user())
                    ? GetRelatedProductDevelopments::make()->forPurchaseOrder($purchaseOrder, $request->user())
                    : [],

                'sampleRequirement' => $purchaseOrder->parent_type === 'OrgSupplier'
                    ? $this->getSampleChoice($purchaseOrder, $this->canEdit && $purchaseOrder->organisation_id === $this->organisation->id)
                    : null,

                'packing_spec_gaps' => GetPurchaseOrderPackingSpecGaps::run($purchaseOrder, $this->organisation),

                'agreed_term_differences' => $purchaseOrder->organisation_id === $this->organisation->id ? CheckAgainstAgreedTerms::make()->forPage($purchaseOrder) : null,

                PurchaseOrderTabsEnum::NOTES->value => $this->tab == PurchaseOrderTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($purchaseOrder, PurchaseOrderTabsEnum::NOTES->value, forAgent: $purchaseOrder->organisation_id !== $this->organisation->id))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($purchaseOrder, PurchaseOrderTabsEnum::NOTES->value, forAgent: $purchaseOrder->organisation_id !== $this->organisation->id))),

                'note_form' => GetAgreement::make()->noteForm($request->user(), $purchaseOrder->group, $purchaseOrder->organisation_id !== $this->organisation->id, $purchaseOrder->organisation_id),

                'note_store_route' => [
                    'name'       => 'grp.models.purchase-order.note.store',
                    'parameters' => [$purchaseOrder->id],
                ],

                PurchaseOrderTabsEnum::COMPLIANCE->value => $this->tab == PurchaseOrderTabsEnum::COMPLIANCE->value ?
                    fn () => PurchaseOrderComplianceDocumentsResource::collection(IndexPurchaseOrderComplianceDocuments::run($purchaseOrder, PurchaseOrderTabsEnum::COMPLIANCE->value))
                    : Inertia::optional(fn () => PurchaseOrderComplianceDocumentsResource::collection(IndexPurchaseOrderComplianceDocuments::run($purchaseOrder, PurchaseOrderTabsEnum::COMPLIANCE->value))),
                'compliance_summary' => $this->tab == PurchaseOrderTabsEnum::COMPLIANCE->value
                    ? fn () => $this->getComplianceSummary($purchaseOrder)
                    : Inertia::optional(fn () => $this->getComplianceSummary($purchaseOrder)),

                PurchaseOrderTabsEnum::ATTACHMENTS->value => $this->tab == PurchaseOrderTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($purchaseOrder))),

                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.purchase-order.attachment.attach',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                    'detachRoute' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.purchase-order.attachment.detach',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                ],
                'attachmentScopes' => PurchaseOrderAttachmentScopeEnum::options(),

                PurchaseOrderTabsEnum::DISPATCHED_EMAILS->value => $this->tab == PurchaseOrderTabsEnum::DISPATCHED_EMAILS->value ?
                    fn () => DispatchedEmailsInOrderResource::collection(IndexDispatchedEmailsInOrder::run(parent: $purchaseOrder, prefix: PurchaseOrderTabsEnum::DISPATCHED_EMAILS->value))
                    : Inertia::optional(fn () => DispatchedEmailsInOrderResource::collection(IndexDispatchedEmailsInOrder::run(parent: $purchaseOrder, prefix: PurchaseOrderTabsEnum::DISPATCHED_EMAILS->value))),

                PurchaseOrderTabsEnum::HISTORY->value => $this->tab == PurchaseOrderTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder, PurchaseOrderTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($purchaseOrder, PurchaseOrderTabsEnum::HISTORY->value))),
            ]
        )->table(
            $draftOrgAgent
                ? IndexAgentCoverBucketItems::make()->tableStructure(PurchaseOrderTabsEnum::ITEMS->value)
                : IndexPurchaseOrderTransactions::make()->tableStructure($purchaseOrder, prefix: PurchaseOrderTabsEnum::ITEMS->value)
        )
            ->table(
                $draftOrgAgent
                    ? IndexAgentCoverBucketItems::make()->tableStructure(PurchaseOrderTabsEnum::PRODUCTS->value)
                    : IndexPurchaseOrderOrgSupplierProducts::make()->tableStructure(prefix: PurchaseOrderTabsEnum::PRODUCTS->value)
            )
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: PurchaseOrderTabsEnum::NOTES->value))
            ->table(IndexPurchaseOrderComplianceDocuments::make()->tableStructure(prefix: PurchaseOrderTabsEnum::COMPLIANCE->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: PurchaseOrderTabsEnum::ATTACHMENTS->value))
            ->table(IndexDispatchedEmailsInOrder::make()->tableStructure(prefix: PurchaseOrderTabsEnum::DISPATCHED_EMAILS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: PurchaseOrderTabsEnum::HISTORY->value));
    }

    private function isAgentViewingOwnOrder(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isAgentOrder()
            && $purchaseOrder->organisation_id !== $this->organisation->id
            && $this->getOrganisationAgent($this->organisation)?->id === $purchaseOrder->agent_id;
    }

    /**
     * A draft through an agent is built on the agent's Ordering page, so its items are shown as they are there.
     */
    private function draftOrgAgent(PurchaseOrder $purchaseOrder): ?OrgAgent
    {
        if ($purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS || $purchaseOrder->parent_type !== 'OrgSupplier' || !$purchaseOrder->agent_id) {
            return null;
        }

        return OrgAgent::where('organisation_id', $purchaseOrder->organisation_id)->where('agent_id', $purchaseOrder->agent_id)->first();
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadExcelAction(PurchaseOrder $purchaseOrder): array
    {
        return [
            'type'    => 'button',
            'style'   => 'tertiary',
            'label'   => 'Excel',
            'tooltip' => __('Download the products of this purchase order'),
            'target'  => '_blank',
            'icon'    => 'fal fa-download',
            'key'     => 'excel',
            'route'   => [
                'name'       => 'grp.org.procurement.purchase_orders.transactions.export',
                'parameters' => [$this->organisation->slug, $purchaseOrder->slug],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acceptsSpreadsheet(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->state == PurchaseOrderStateEnum::IN_PROCESS
            && ($purchaseOrder->parent instanceof OrgSupplier || $purchaseOrder->parent instanceof OrgPartner);
    }

    private function uploadExcel(PurchaseOrder $purchaseOrder): array
    {
        return [
            'title'               => [
                'label'       => __('Upload products'),
                'information' => $purchaseOrder->parent_type === 'OrgPartner'
                    ? __('Columns: code (our SKO code) and quantity (in SKOs). SKOs already on the order get the new quantity.')
                    : __('Columns: code (the supplier product code) and quantity. Products already on the order get the new quantity.'),
            ],
            'progressDescription' => __('Adding products'),
            'preview_template'    => [
                'header' => ['code', 'quantity'],
                'rows'   => [
                    ['code' => 'Product-001', 'quantity' => '10'],
                ],
            ],
            'upload_spreadsheet'  => [
                'event'           => 'action-progress',
                'channel'         => 'grp.personal.'.request()->user()->id,
                'required_fields' => ['code', 'quantity'],
                'template'        => [
                    'label' => __('Download this order (.xlsx)'),
                ],
                'route'           => [
                    'upload'   => [
                        'name'       => 'grp.models.purchase-order.transaction.upload',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                    'history'  => [
                        'name'       => 'grp.json.purchase_order.transaction.recent_uploads',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                    'download' => [
                        'name'       => 'grp.org.procurement.purchase_orders.transactions.export',
                        'parameters' => [$this->organisation->slug, $purchaseOrder->slug],
                    ],
                ],
            ],
        ];
    }

    private function emailToSupplierAction(PurchaseOrder $purchaseOrder): ?array
    {
        $supplier = $purchaseOrder->parent instanceof OrgSupplier && !$purchaseOrder->isAgentOrder() ? $purchaseOrder->parent->supplier : null;

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
                'pdfUrl'  => route('grp.org.procurement.purchase_orders.pdf', [$this->organisation->slug, $purchaseOrder->slug, PdfPurchaseOrder::make()->filename($purchaseOrder)]),
            ]
        ];
    }

    public function getActions(PurchaseOrder $purchaseOrder, bool $showProductsTab): array
    {
        $actions = match ($purchaseOrder->state) {
            PurchaseOrderStateEnum::IN_PROCESS => [
                $showProductsTab || $purchaseOrder->parent instanceof OrgPartner ? [
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
                $this->acceptsSpreadsheet($purchaseOrder) ? $this->downloadExcelAction($purchaseOrder) : [],
                $this->acceptsSpreadsheet($purchaseOrder) ? [
                    'label'   => __('Upload products'),
                    'tooltip' => __('Add products, or change their quantities, from a spreadsheet'),
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'icon'    => 'fal fa-upload',
                    'key'     => 'upload_products',
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
                    'send_channels' => SendPartnerPurchaseOrderToSeller::appliesTo($purchaseOrder) ? [] : SendPurchaseOrderToSupplier::channels($purchaseOrder),
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
                    'label'   => __('Delivery date'),
                    'tooltip' => __('Change estimated delivery date'),
                    'type'    => 'button',
                    'style'   => 'secondary',
                    'icon'    => 'fal fa-calendar-alt',
                    'key'     => 'edit_estimated_delivery_date',
                    'estimated_receiving_date' => $purchaseOrder->estimatedReceivingDate(),
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
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
                    'icon'    => 'fal fa-ban',
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
                    'estimated_receiving_date' => $purchaseOrder->estimatedReceivingDate(),
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.purchase-order.update',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                    ],
                ],
                !StoreStockDeliveryFromPurchaseOrder::transactionsAwaitingDelivery($purchaseOrder)->exists() ? [] : [
                    'label'   => $this->hasActiveStockDelivery($purchaseOrder) ? __('Deliver remaining items') : __('New Delivery'),
                    'tooltip' => $this->hasActiveStockDelivery($purchaseOrder) ? __('Create a stock delivery with the items still to come') : __('Create Stock Delivery from this Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'create',
                    'icon'    => 'fal fa-plus',
                    'key'     => 'new_stock_delivery',
                    'disabled_reason' => StoreStockDeliveryFromPurchaseOrder::partnerDeliveryBlockedReason($purchaseOrder) ?? StoreStockDeliveryFromPurchaseOrder::sampleBlockedReason($purchaseOrder),
                    'agent_note'      => $purchaseOrder->isAgentOrder() ? __('Containers are now made by the agent. Making one here is allowed for a short while only and will soon be removed: if this is not for an agent container you already agreed, leave it to the agent.') : null,
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
                $this->hasActiveStockDelivery($purchaseOrder) ? [] : [
                    'label'   => __('Cancel'),
                    'tooltip' => __('Cancel Purchase Order'),
                    'type'    => 'button',
                    'style'   => 'delete',
                    'icon'    => 'fal fa-ban',
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
            default => []
        };

        if ($this->datedByItsStockDeliveries($purchaseOrder)) {
            $actions = array_values(array_filter($actions, fn (array $action) => Arr::get($action, 'key') !== 'edit_estimated_delivery_date'));
        }

        return $actions;
    }

    private function arrangePageHeadActions(array $actions): array
    {
        $actions   = array_values(array_filter($actions));
        $isCancel  = fn (array $action) => Arr::get($action, 'key') === 'cancel_purchase_order';
        $isEdit    = fn (array $action) => Arr::get($action, 'style') === 'edit';
        $hasSubmit = collect($actions)->contains(fn (array $action) => Arr::get($action, 'key') === 'submit_purchase_order');

        return [
            ...array_filter($actions, $isCancel),
            ...array_filter($actions, fn (array $action) => !$isCancel($action) && ($hasSubmit || !$isEdit($action))),
            ...($hasSubmit ? [] : array_filter($actions, $isEdit)),
        ];
    }

    public function getAgentActions(PurchaseOrder $purchaseOrder): array
    {
        return array_values(array_filter(
            $this->getActions($purchaseOrder, false),
            fn (array $action) => in_array(Arr::get($action, 'key'), self::AGENT_ACTION_KEYS, true)
        ));
    }

    /**
     * Once an agent order's items are in stock deliveries, those deliveries carry the delivery dates.
     */
    private function datedByItsStockDeliveries(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->isAgentOrder() && $this->hasActiveStockDelivery($purchaseOrder);
    }

    public function getComplianceSummary(PurchaseOrder $purchaseOrder): array
    {
        $compliance = GetPurchaseOrderCompliance::run($purchaseOrder);
        $canUpload  = ($this->canEdit && $purchaseOrder->organisation_id === $this->organisation->id) || $this->agentCanEdit;

        return [
            'eta'          => $compliance['eta'],
            'deadline'     => $compliance['deadline'],
            'buffer_days'  => GetPurchaseOrderCompliance::bufferDays($purchaseOrder),
            'missing'      => $compliance['missing'],
            'overdue'      => $compliance['overdue'],
            'upload_route' => $canUpload ? [
                'name'       => 'grp.models.purchase-order.compliance.attach',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ] : null,
        ];
    }

    /**
     * The stage tracker replaces the state timeline once a supplier or agent order is sent.
     */
    public function getStages(PurchaseOrder $purchaseOrder): ?array
    {
        if ($purchaseOrder->parent_type === 'OrgPartner' || in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED])) {
            return null;
        }

        $buyerCanEdit   = $this->canEdit && $purchaseOrder->organisation_id === $this->organisation->id;
        $markableFields = match (true) {
            $this->agentCanEdit => PurchaseOrderStageEnum::AGENT_MARKABLE_FIELDS,
            $buyerCanEdit       => array_keys(PurchaseOrderStageEnum::MARKABLE_FIELDS),
            default             => [],
        };

        return [
            'stages'       => GetPurchaseOrderStages::make()->forUi($purchaseOrder, $markableFields),
            'route'        => [
                'name'       => 'grp.models.purchase-order.stage',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ],
            'upload_route' => $buyerCanEdit || $this->agentCanEdit ? [
                'name'       => 'grp.models.purchase-order.attachment.attach',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ] : null,
            'deposit'      => $this->getDepositChoice($purchaseOrder, $buyerCanEdit),
            'sample'       => $this->getSampleChoice($purchaseOrder, $buyerCanEdit),
        ];
    }

    /**
     * Whether the goods may ship before the sample is approved: the buyer sets it, and records why when they ship
     * without an approved sample. Agents see it but cannot change it.
     */
    private function getSampleChoice(PurchaseOrder $purchaseOrder, bool $buyerCanEdit): ?array
    {
        if (!$buyerCanEdit && !$purchaseOrder->sample_required) {
            return null;
        }

        return [
            'required'        => $purchaseOrder->sample_required,
            'approved'        => $purchaseOrder->sample_approved_at !== null,
            'override_reason' => $purchaseOrder->sample_override_reason,
            'route'           => $buyerCanEdit ? [
                'name'       => 'grp.models.purchase-order.update',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ] : null,
        ];
    }

    /**
     * Some suppliers start production without a deposit: the buyer or the agent can say so on an agent order
     * while nothing is paid and no deposit amount is set, and take it back the same way.
     */
    private function getDepositChoice(PurchaseOrder $purchaseOrder, bool $buyerCanEdit): ?array
    {
        if (!$purchaseOrder->isAgentOrder() || !($buyerCanEdit || $this->agentCanEdit)
            || $purchaseOrder->deposit_paid_at !== null
            || (float) $purchaseOrder->deposit_amount > 0
            || $purchaseOrder->depositPayments()->exists()) {
            return null;
        }

        return [
            'not_needed' => $purchaseOrder->deposit_amount !== null,
            'route'      => [
                'name'       => 'grp.models.purchase-order.update',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ],
        ];
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
                'tooltip'   => $purchaseOrder->isAgentOrder()
                    ? __('Estimated date the supplier dispatches the goods to the agent')
                    : __('Estimated date the supplier dispatches the goods'),
                'key'       => 'estimated_dispatch',
                'icon'      => 'fal fa-truck',
                'sub_label' => $estimatedProductionDate ? null : __('No estimated production date'),
                'timestamp' => $estimatedProductionDate,
            ];
        }

        if (!$hasReceived && !$this->datedByItsStockDeliveries($purchaseOrder)) {
            // TODO: Default should come from the Supplier/Agent "Delivery time (days)" (no such field yet).
            // While the purchase order is not confirmed, only a sub label should show
            // (e.g. "Estimated 30 days after confirmation"); once confirmed, the default timestamp is
            // calculated as estimated dispatch + delivery days, and once dispatched it becomes the
            // stock delivery estimated received date.
            $estimatedReceivingDate = $purchaseOrder->estimatedReceivingDate();

            $timeline['estimated_delivery'] = [
                'label'     => __('Estimated delivery'),
                'tooltip'   => __('Estimated date the goods arrive at the delivery address'),
                'key'       => 'estimated_delivery',
                'sub_label' => $estimatedReceivingDate ? null : __('No estimated delivery date'),
                'timestamp' => $estimatedReceivingDate,
            ];
        }

        return $timeline;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * An agent order whose agent pays the factories from its ledger for that client is paid through the agent's money requests, not by paying the factory from here:
     * the goods value, what the agent asked for and what its ledger shows paid to the factory.
     *
     * @return array{currency_code: string, goods: float, requested: float, paid_to_suppliers: float, to_pay: float, route: array{name: string, parameters: array<string, mixed>, label: string}|null}|null
     */
    private function getAgentMoney(PurchaseOrder $purchaseOrder): ?array
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return null;
        }

        $paysFactoriesFromLedger = DB::table('agent_payments')
            ->where('agent_id', $purchaseOrder->agent_id)
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->whereIn('type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('deleted_at')
            ->exists()
            || DB::table('deposit_request_items')->where('purchase_order_id', $purchaseOrder->id)->exists();

        if (!$paysFactoriesFromLedger) {
            return null;
        }

        $requested = DB::table('deposit_request_items')
            ->join('deposit_requests', 'deposit_requests.id', 'deposit_request_items.deposit_request_id')
            ->where('deposit_request_items.purchase_order_id', $purchaseOrder->id)
            ->where('deposit_requests.state', '!=', DepositRequestStateEnum::CANCELLED->value)
            ->sum('deposit_request_items.amount');
        $paidToSuppliers = DB::table('agent_payments')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereIn('type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('deleted_at')
            ->sum('amount');
        $goods = round((float) $purchaseOrder->cost_items, 2);
        $paid  = round((float) $paidToSuppliers, 2);

        return [
            'currency_code'     => $purchaseOrder->currency->code,
            'goods'             => $goods,
            'requested'         => round((float) $requested, 2),
            'paid_to_suppliers' => $paid,
            'to_pay'            => max(0, round($goods - $paid, 2)),
            'route'             => $this->agentMoneyRoute($purchaseOrder),
        ];
    }

    /**
     * @return array{name: string, parameters: array<string, mixed>, label: string}|null
     */
    private function agentMoneyRoute(PurchaseOrder $purchaseOrder): ?array
    {
        if ($this->isAgentViewingOwnOrder($purchaseOrder)) {
            return $this->agentCanEdit && in_array($purchaseOrder->state, [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED], true) ? [
                'name'       => 'grp.org.agent.accounting.deposit_requests.create',
                'parameters' => ['organisation' => $this->organisation->slug, 'client' => $purchaseOrder->organisation->slug, 'purchase_order' => $purchaseOrder->id],
                'label'      => __('Request money'),
            ] : null;
        }

        $orgAgent = OrgAgent::where('organisation_id', $purchaseOrder->organisation_id)->where('agent_id', $purchaseOrder->agent_id)->first();

        return $orgAgent ? [
            'name'       => 'grp.org.procurement.org_agents.show',
            'parameters' => ['organisation' => $purchaseOrder->organisation->slug, 'orgAgent' => $orgAgent->slug, 'tab' => 'money'],
            'label'      => __('Money with the agent'),
        ] : null;
    }

    private function getPayments(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $totalToPay = SyncPurchaseOrderDepositPaid::totalToPay($purchaseOrder);
        $paid       = SyncPurchaseOrderDepositPaid::paidAmount($purchaseOrder);

        return [
            'can_confirm'   => ConfirmPurchaseOrderPayment::canConfirm($purchaseOrder, $request->user()),
            'currency_code' => $purchaseOrder->currency?->code,
            'methods'       => PurchaseOrderPaymentMethodEnum::options(),
            'total'         => $totalToPay,
            'paid'          => $paid,
            'left'          => max(0, round($totalToPay - $paid, 2)),
            'balance_awaits_qc' => ConfirmPurchaseOrderPayment::balanceAwaitsQc($purchaseOrder),
            'paid_at'       => $purchaseOrder->deposit_paid_at,
            'deposits'      => $purchaseOrder->depositPayments()
                ->with(['bankStatement:id,ulid,file_name', 'createdBy:id,contact_name,username'])
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->map(fn (PurchaseOrderDepositPayment $payment) => [
                    'id'             => $payment->id,
                    'date'           => $payment->date->toDateString(),
                    'amount'         => (float) $payment->amount,
                    'method'         => PurchaseOrderPaymentMethodEnum::labels()[$payment->method->value] ?? $payment->method->value,
                    'reference'      => $payment->reference,
                    'paid_by'        => $payment->createdBy?->contact_name ?: $payment->createdBy?->username,
                    'bank_statement' => $payment->bankStatement ? ['ulid' => $payment->bankStatement->ulid, 'caption' => $payment->bankStatement->file_name] : null,
                    'undo_route'     => [
                        'name'       => 'grp.models.purchase-order.payment.undo',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id, 'depositPayment' => $payment->id],
                    ],
                ])
                ->values(),
            'confirm_route' => [
                'name'       => 'grp.models.purchase-order.payment.confirm',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
            ],
        ];
    }

    private function getDeposits(PurchaseOrder $purchaseOrder): array
    {
        $deposits = $purchaseOrder->deposits()->with('currency')->orderBy('id')->get();

        return [
            'can_edit' => $this->canEdit,
            'list'     => $deposits->map(fn ($deposit) => [
                'id'                  => $deposit->id,
                'reference'           => $deposit->reference,
                'amount'              => $deposit->amount,
                'currency_id'         => $deposit->currency_id,
                'currency_code'       => $deposit->currency->code,
                'state'               => $deposit->state->value,
                'state_label'         => $deposit->state->labels()[$deposit->state->value],
                'paid_to_supplier_at' => $deposit->paid_to_supplier_at,
                'unapplied_amount'    => $deposit->unapplied_amount,
                'notes'               => $deposit->notes,
                'applications'        => $this->getApplicationHistory($deposit),
                'updateRoute'         => [
                    'name'       => 'grp.models.aspo-deposit.update',
                    'parameters' => ['aspoDeposit' => $deposit->id],
                    'method'     => 'patch',
                ],
                'stateRoute'          => [
                    'name'       => 'grp.models.aspo-deposit.state',
                    'parameters' => ['aspoDeposit' => $deposit->id],
                    'method'     => 'patch',
                ],
            ])->all(),
            'storeRoute' => [
                'name'       => 'grp.models.purchase-order.deposit.store',
                'parameters' => ['purchaseOrder' => $purchaseOrder->id],
                'method'     => 'post',
            ],
            'currency_code' => $purchaseOrder->currency->code,
            'currency_id'   => $purchaseOrder->currency_id,
            'currencies'    => Currency::orderBy('code')->get(['id', 'code'])->toArray(),
        ];
    }

    private function getApplicationHistory(AspoDeposit $deposit): array
    {
        return $deposit->stockDeliveryApplications()
            ->withTrashed()
            ->with(['stockDelivery', 'createdBy', 'deletedBy'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($application) => [
                'id'                       => $application->id,
                'amount'                   => $application->amount,
                'stock_delivery_reference' => $application->stockDelivery?->reference,
                'created_by_name'          => $application->createdBy?->contact_name ?? $application->createdBy?->username,
                'created_at'               => $application->created_at,
                'is_removed'               => $application->trashed(),
                'deleted_by_name'          => $application->deletedBy?->contact_name ?? $application->deletedBy?->username,
                'deleted_at'               => $application->deleted_at,
                'deleteRoute'              => !$application->trashed() && $this->canEdit ? [
                    'name'       => 'grp.models.stock-delivery-deposit-application.delete',
                    'parameters' => ['stockDeliveryDepositApplication' => $application->id],
                    'method'     => 'delete',
                ] : null,
            ])->all();
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
            ->orderBy('stock_deliveries.id')
            ->get()->map(fn (StockDelivery $stockDelivery) => [
            'reference'  => $stockDelivery->reference,
            'state'      => $stockDelivery->state->value,
            'state_icon' => StockDeliveryStateEnum::stateIcon()[$stockDelivery->state->value],
            'timeline'   => ShowStockDelivery::make()->getTimeline($stockDelivery, withPurchaseOrderStates: false),
            'route'      => [
                'name'       => 'grp.org.procurement.stock_deliveries.show',
                'parameters' => [
                    'organisation'  => $this->organisation->slug,
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
        $organisationAgent = $this->getOrganisationAgent($this->organisation);
        $query             = $organisationAgent
            ? PurchaseOrder::where('agent_id', $organisationAgent->id)
            : PurchaseOrder::where('organisation_id', $purchaseOrder->organisation_id);

        $routeName = $request->route()->getName();

        if ($routeName === 'grp.org.procurement.org_agents.show.purchase-orders.show') {
            $query->where('agent_id', $purchaseOrder->agent_id);
        } elseif ($routeName === 'grp.org.agent.incoming_purchase_orders.show') {
            GetAgentIncomingPurchaseOrdersCount::whereIncoming($query);
        } elseif ($routeName !== 'grp.org.procurement.purchase_orders.show') {
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
            'grp.org.procurement.purchase_orders.show', 'grp.org.agent.incoming_purchase_orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
            'grp.org.procurement.org_agents.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'orgAgent'      => $purchaseOrder->orgAgentOfOrder()?->slug,
                        'purchaseOrder' => $purchaseOrder->slug,
                    ],
                ],
            ],
            'grp.org.procurement.org_suppliers.show.purchase-orders.show' => [
                'label' => $purchaseOrder->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
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
                        'organisation'  => $this->organisation->slug,
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
            'grp.org.agent.incoming_purchase_orders.show' => array_merge(
                IndexPurchaseOrders::make()->getBreadcrumbs('grp.org.agent.incoming_purchase_orders.index', Arr::except($routeParameters, ['purchaseOrder'])),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => $purchaseOrder->reference,
                            'route' => [
                                'name'       => 'grp.org.agent.incoming_purchase_orders.show',
                                'parameters' => $routeParameters,
                            ],
                        ],
                        'suffix' => $suffix,
                    ],
                ]
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

    /**
     * The order a sister company's purchase order became in their shop, linked when the user can see that shop's orders.
     *
     * @return array{reference: string, url: string|null}|null
     */
    private function sellerOrder(PurchaseOrder $purchaseOrder, ActionRequest $request): ?array
    {
        $order = Order::find(Arr::get($purchaseOrder->data, 'seller_order_id'));
        if (!$order) {
            return null;
        }

        return [
            'reference' => $order->reference,
            'url'       => $request->user()->authTo(["orders.$order->shop_id.view", "accounting.$order->organisation_id.view"])
                ? route('grp.org.shops.show.ordering.orders.show', [$order->organisation->slug, $order->shop->slug, $order->slug])
                : null,
        ];
    }


    /**
     * The supplier's rough per unit expense estimate (from the product upload) times the quantity ordered,
     * a budgeting hint shown next to the real total.
     */
    public function estimatedExpenses(PurchaseOrder $purchaseOrder): float
    {
        return (float)$purchaseOrder->purchaseOrderTransactions()
            ->join('supplier_products', 'supplier_products.id', '=', 'purchase_order_transactions.supplier_product_id')
            ->whereRaw("supplier_products.data->>'unit_expense' ~ '^[0-9.]+$'")
            ->groupBy('supplier_products.currency_id')
            ->selectRaw("supplier_products.currency_id, sum(purchase_order_transactions.quantity_ordered * (supplier_products.data->>'unit_expense')::numeric) as expenses")
            ->get()
            ->sum(function ($row) use ($purchaseOrder): float {
                if ($row->currency_id === $purchaseOrder->currency_id) {
                    return (float)$row->expenses;
                }

                $supplierCurrency = Currency::find($row->currency_id);
                $rate             = GetHistoricCurrencyExchange::run($supplierCurrency, $purchaseOrder->currency, $purchaseOrder->date)
                    ?? GetCurrencyExchange::run($supplierCurrency, $purchaseOrder->currency);

                return (float)$row->expenses * (float)$rate;
            });
    }
}
