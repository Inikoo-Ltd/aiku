<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\StockDelivery\UI;

use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\Procurement\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property StockDelivery $stockDelivery
 */
class ShowStockDelivery extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->maya) {
            return true;
        }
        $this->canEdit = true;
        //TODO: Need to think of this
        return true;
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($organisation, $request)->withTab(StockDeliveryTabsEnum::values());
        $this->stockDelivery    = $stockDelivery;
        return $this->handle($stockDelivery);
    }

    public function maya(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->maya   = true;
        $this->initialisation($organisation, $request)->withTab(StockDeliveryTabsEnum::values());
        $this->stockDelivery = $stockDelivery;
    }

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        return $stockDelivery;
    }

    public function htmlResponse(StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $this->validateAttributes();


        return Inertia::render(
            'Procurement/StockDelivery',
            [
                'title'                                 => __('supplier delivery'),
                'breadcrumbs'                           => $this->getBreadcrumbs($this->stockDelivery, $request->route()->originalParameters()),
                // 'navigation'                            => [
                //     'previous' => $this->getPrevious($this->stockDelivery, $request),
                //     'next'     => $this->getNext($this->stockDelivery, $request),
                // ],
                'pageHead'    => [
                    'icon'  => ['fal', 'people-arrows'],
                    'title' => $this->stockDelivery->reference,
                    'edit'  => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name' => 'grp.models.stock-delivery.attachment.attach',
                        'parameters' => [
                            'stockDelivery' => $this->stockDelivery->id,
                        ]
                    ],
                    'detachRoute' => [
                        'name' => 'grp.models.stock-delivery.attachment.detach',
                        'parameters' => [
                            'stockDelivery' => $this->stockDelivery->id,
                        ],
                        'method' => 'delete'
                    ]
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => StockDeliveryTabsEnum::navigation()
                ],
                StockDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == StockDeliveryTabsEnum::ATTACHMENTS->value ?
                fn () => AttachmentsResource::collection(IndexAttachments::run($this->stockDelivery))
                : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($this->stockDelivery))),
            ]
        )->table(IndexAttachments::make()->tableStructure(
            prefix: StockDeliveryTabsEnum::ATTACHMENTS->value
        ));
    }


    public function jsonResponse(): StockDeliveryResource
    {
        return new StockDeliveryResource($this->stockDelivery);
    }

    public function getBreadcrumbs(StockDelivery $stockDelivery, array $routeParameters, string $suffix = ''): array
    {
        return array_merge(
            (new ShowProcurementDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.org.procurement.stock_deliveries.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Supplier delivery')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.stock_deliveries.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $stockDelivery->reference,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ]
        );
    }

    // public function getPrevious(StockDelivery $stockDelivery, ActionRequest $request): ?array
    // {
    //     $previous = StockDelivery::where('number', '<', $stockDelivery->number)->orderBy('number', 'desc')->first();
    //     return $this->getNavigation($previous, $request->route()->getName());

    // }

    // public function getNext(StockDelivery $stockDelivery, ActionRequest $request): ?array
    // {
    //     $next = StockDelivery::where('number', '>', $stockDelivery->number)->orderBy('number')->first();
    //     return $this->getNavigation($next, $request->route()->getName());
    // }

    // private function getNavigation(?StockDelivery $stockDelivery, string $routeName): ?array
    // {
    //     if (!$stockDelivery) {
    //         return null;
    //     }
    //     return match ($routeName) {
    //         'grp.org.procurement.stock_deliveries.show' => [
    //             'label' => $stockDelivery->reference,
    //             'route' => [
    //                 'name'      => $routeName,
    //                 'parameters' => [
    //                     'employee' => $stockDelivery->number
    //                 ]

    //             ]
    //         ]
    //     };
    // }
}
