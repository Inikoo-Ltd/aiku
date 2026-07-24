<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryItems;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStockDelivery extends OrgAction
{
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
        $this->initialisation($organisation, $request)->withTab(StockDeliveryTabsEnum::values());

        return $this->handle($stockDelivery);
    }

    public function maya(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->maya          = true;
        $this->stockDelivery = $stockDelivery;

        $this->initialisation($organisation, $request)->withTab(StockDeliveryTabsEnum::values());
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
                        'label' => $stockDelivery->state->labels()[$stockDelivery->state->value],
                    ],
                    'edit'       => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters()),
                        ],
                    ] : false,
                ],
                'stock_delivery'   => StockDeliveryResource::make($stockDelivery)->toArray($request),
                'timelines'        => $this->getTimeline($stockDelivery),
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => StockDeliveryTabsEnum::navigation(),
                ],
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
                    fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value))
                    : Inertia::optional(fn () => StockDeliveryItemResource::collection(IndexStockDeliveryItems::run($stockDelivery, StockDeliveryTabsEnum::ITEMS->value))),

                StockDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == StockDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($stockDelivery))),

                StockDeliveryTabsEnum::HISTORY->value => $this->tab == StockDeliveryTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($stockDelivery, StockDeliveryTabsEnum::HISTORY->value))),
            ]
        )->table(IndexStockDeliveryItems::make()->tableStructure($stockDelivery, prefix: StockDeliveryTabsEnum::ITEMS->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: StockDeliveryTabsEnum::ATTACHMENTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: StockDeliveryTabsEnum::HISTORY->value));
    }

    public function jsonResponse(): StockDeliveryResource
    {
        return new StockDeliveryResource($this->stockDelivery);
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

    public function getTimeline(StockDelivery $stockDelivery, bool $withPurchaseOrderStates = true): array
    {
        $purchaseOrder = $withPurchaseOrderStates ? $stockDelivery->purchaseOrders()->first() : null;

        $timeline = $purchaseOrder ? $this->getPurchaseOrderTimeline($purchaseOrder) : [];

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
                StockDeliveryStateEnum::CONFIRMED     => Arr::get($stockDelivery->data, 'confirmed_at'),
                StockDeliveryStateEnum::READY_TO_SHIP => Arr::get($stockDelivery->data, 'ready_to_ship_at'),
                default                               => $stockDelivery->{$case->snake() . '_at'} ?: null
            };

            if (in_array($case, $hiddenUnlessCurrent, true) && $stockDelivery->state != $case && !$timestamp) {
                continue;
            }

            $estimatedTimestamp = $timestamp ? null : match ($case) {
                StockDeliveryStateEnum::DISPATCHED => Arr::get($stockDelivery->data, 'estimated_dispatched_date'),
                StockDeliveryStateEnum::RECEIVED   => Arr::get($stockDelivery->data, 'estimated_receiving_date'),
                default                            => null
            };

            $label = $case == StockDeliveryStateEnum::IN_PROCESS && $purchaseOrder
                ? __('Created')
                : $case->labels()[$case->value];

            $timeline[$case->value] = [
                'label'             => $label,
                'tooltip'           => $case->labels()[$case->value],
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
}
