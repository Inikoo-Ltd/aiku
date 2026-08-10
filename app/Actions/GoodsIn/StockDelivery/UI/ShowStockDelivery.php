<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDelivery\Traits\WithStockDeliveryWeightAndVolume;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryItems;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryUnderOverDeliveredItems;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Http\Resources\Procurement\OrgSupplierResource;
use App\Http\Resources\Procurement\StockDeliveryItemCostResource;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Http\Resources\Procurement\StockDeliveryUnderOverDeliveredItemResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStockDelivery extends OrgAction
{
    use WithStockDeliveryWeightAndVolume;
    use WithAgentOrganisation;

    public function authorize(): bool
    {
        if ($this->maya) {
            return true;
        }

        $this->canEdit = true;

        // TODO: Need to think of this
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

    public function maya(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->maya          = true;
        $this->stockDelivery = $stockDelivery;

        $this->initialisation($organisation, $request)->withTab($this->getTabs($stockDelivery));
        $this->authorizeProcurementRecord($stockDelivery);
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
                        'label' => $this->getStateLabels($stockDelivery)[$stockDelivery->state->value],
                    ],
                    'edit'       => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                    ] : false,
                    'actions'    => $this->canEdit ? $this->getActions($stockDelivery) : [],
                ],
                'stock_delivery'   => StockDeliveryResource::make($stockDelivery)->toArray($request),
                'timelines'        => $this->getTimeline($stockDelivery),
                'purchase_order'   => $this->getPurchaseOrderLink($stockDelivery),
                'box_stats'        => $this->getBoxStats($stockDelivery, $request),
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => $this->getTabsNavigation($stockDelivery),
                ],
                'costing'          => $this->getCosting($stockDelivery),
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

                StockDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == StockDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))),

                StockDeliveryTabsEnum::HISTORY->value => $this->tab == StockDeliveryTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value))),
            ]
        )->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::ITEMS->value))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::PENDING_ITEMS->value))
            ->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::DONE_ITEMS->value))
            ->table(IndexStockDeliveryUnderOverDeliveredItems::make()->tableStructure(prefix: StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: StockDeliveryTabsEnum::ATTACHMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: StockDeliveryTabsEnum::HISTORY->value));
    }

    public function jsonResponse(): StockDeliveryResource
    {
        return new StockDeliveryResource($this->stockDelivery);
    }

    public function getPurchaseOrderLink(StockDelivery $stockDelivery): ?array
    {
        $purchaseOrder = $stockDelivery->purchaseOrders()->first();

        if (!$purchaseOrder) {
            return null;
        }

        return [
            'reference' => $purchaseOrder->reference,
            'route'     => [
                'name'       => 'grp.org.procurement.purchase_orders.show',
                'parameters' => [
                    'organisation'  => $stockDelivery->organisation->slug,
                    'purchaseOrder' => $purchaseOrder->slug,
                ],
            ],
        ];
    }

    public function getPurchaseOrderTimeline(PurchaseOrder $purchaseOrder): array
    {
        $labels = PurchaseOrderStateEnum::labels();

        $states = [
            PurchaseOrderStateEnum::IN_PROCESS->value => $purchaseOrder->created_at,
            PurchaseOrderStateEnum::SUBMITTED->value  => $purchaseOrder->submitted_at,
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
                    'organisation'  => $stockDelivery->organisation->slug,
                    'stockDelivery' => $stockDelivery->slug,
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
                [
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
                ],
            ],
            StockDeliveryStateEnum::CHECKED => $hasPlacements ? [] : [
                [
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
                ],
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

        return array_merge($actions, [$pdfButton]);
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
        ];
    }

    public function getTimeline(StockDelivery $stockDelivery, bool $withPurchaseOrderStates = true): array
    {
        $purchaseOrder = $withPurchaseOrderStates ? $stockDelivery->purchaseOrders()->first() : null;

        $timeline = $purchaseOrder ? $this->getPurchaseOrderTimeline($purchaseOrder) : [];

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

            $label = $case == StockDeliveryStateEnum::IN_PROCESS && $purchaseOrder
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
        $items = IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value);

        return $stockDelivery->state === StockDeliveryStateEnum::PLACED
            ? StockDeliveryItemCostResource::collection($items)
            : StockDeliveryItemResource::collection($items);
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
            'can_edit'                   => $this->canEdit,
            'currency'                   => $stockDelivery->currency?->code,
            'checklist'                  => $checklist,
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
        ];
    }

    private function getDepositSettlement(StockDelivery $stockDelivery, $applications, float $agentInvoiceAmount, float $depositsTotal): array
    {
        $availableDeposits = $stockDelivery->agent_id
            ? \App\Models\SupplyChain\AspoDeposit::where('agent_id', $stockDelivery->agent_id)
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
                'deleteRoute' => $this->canEdit ? [
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
