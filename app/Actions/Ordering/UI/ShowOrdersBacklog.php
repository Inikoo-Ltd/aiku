<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:40:27 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\OrgAction;
use App\Enums\UI\Ordering\OrdersBacklogTabsEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrdersBacklog extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("orders.{$this->organisation->id}.view");
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(OrdersBacklogTabsEnum::values());

        return $shop;
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }


    public function htmlResponse(Organisation|Shop $parent, ActionRequest $request): Response
    {

        $currency = '_org_currency';

        if ($parent instanceof Shop) {
            $currency = '';
        }

        $currencyCode =  $parent->currency->code;

        $icon = 'fal fa-tachometer-alt';

        $tabsBox = [
            [
                'label' => __('In Basket'),
                'currency_code' => $currencyCode,
                'tabs' => [
                    [
                        'tab_slug' => 'in_basket',
                        'label' => $parent->orderHandlingStats->number_orders_state_creating,
                        'type' => 'number',
                        'icon' => $icon,
                        'information' => [
                            'type' => 'currency',
                            'label' => $parent->orderHandlingStats->{"orders_state_creating_amount$currency"},
                        ]
                    ]
                ]
            ],
            [
                'label' => __('Submitted'),
                'currency_code' => $currencyCode,
                'tabs' => [
                    [
                        'tab_slug' => 'submitted_paid',
                        'label' => $parent->orderHandlingStats->number_orders_state_submitted_paid,
                        'type' => 'number',
                        'icon' => $icon,
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_submitted_paid_amount$currency"},
                            'type' => 'currency'
                        ]
                        ],
                    [
                        'tab_slug' => 'submitted_unpaid',
                        'label' => $parent->orderHandlingStats->number_orders_state_submitted_not_paid,
                        'type' => 'number',
                        'icon' => $icon,
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_submitted_not_paid_amount$currency"},
                            'type' => 'currency'
                        ]
                    ]
                ]
            ],
            [
                'label' => __('Picking'),
                'currency_code' => $currencyCode,
                'tabs' => [
                    [
                        'tab_slug' => 'picking',
                        'label' => $parent->orderHandlingStats->number_orders_state_handling,
                        'type' => 'number',
                        'icon' => $icon,
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_handling_amount$currency"},
                            'type' => 'currency'
                        ]
                    ],
                    [
                        'tab_slug' => 'blocked',
                        'label' => $parent->orderHandlingStats->number_orders_state_handling_blocked,
                        'type' => 'number',
                        'icon' => $icon,
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_handling_blocked_amount$currency"},
                            'type' => 'currency',
                        ]
                    ]
                ]
            ],
            [
                'label' => __('Invoicing'),
                'currency_code' => $currencyCode,
                'tabs' => [
                    [
                        'tab_slug' => 'packed',
                        'label' =>  $parent->orderHandlingStats->number_orders_state_packed,
                        'icon' => 'fal fa-box',
                        // 'indicator' => true,
                        'iconClass' => 'text-teal-500',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_packed_amount$currency"},
                            'type' => 'currency'
                        ]
                    ],
                    [
                        'tab_slug' => 'packed_done',
                        'label' => $parent->orderHandlingStats->number_orders_state_finalised,
                        'icon' => 'fal fa-box-check',
                        'iconClass' => 'text-orange-500',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_finalised_amount$currency"},
                            'type' => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label' => __('Today'),
                'currency_code' => $currencyCode,
                'tabs' => [
                    [
                        'tab_slug' => 'dispatched_today',
                        'label' => $parent->orderHandlingStats->number_orders_dispatched_today,
                        'type'  => 'number',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_dispatched_today_amount$currency"},
                            'type' => 'currency'
                        ]
                    ],
                ]
            ]
        ];
        return Inertia::render(
            'Ordering/OrdersBacklog',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->originalParameters()
                ),
                'title'       => __('orders backlog'),
                'pageHead'    => [
                    'title'     => __('orders backlog'),

                ],


                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $tabsBox
                ],


                OrdersBacklogTabsEnum::IN_BASKET->value => $this->tab == OrdersBacklogTabsEnum::IN_BASKET->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))),

                OrdersBacklogTabsEnum::SUBMITTED_PAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_PAID->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))),

                OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value => $this->tab == OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))),

                OrdersBacklogTabsEnum::PICKING->value => $this->tab == OrdersBacklogTabsEnum::PICKING->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PICKING->value, bucket: OrdersBacklogTabsEnum::PICKING->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PICKING->value, bucket: OrdersBacklogTabsEnum::PICKING->value))),

                OrdersBacklogTabsEnum::BLOCKED->value => $this->tab == OrdersBacklogTabsEnum::BLOCKED->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::BLOCKED->value, bucket: OrdersBacklogTabsEnum::BLOCKED->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::BLOCKED->value, bucket: OrdersBacklogTabsEnum::BLOCKED->value))),

                OrdersBacklogTabsEnum::PACKED->value => $this->tab == OrdersBacklogTabsEnum::PACKED->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))),

                OrdersBacklogTabsEnum::PACKED_DONE->value => $this->tab == OrdersBacklogTabsEnum::PACKED_DONE->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED_DONE->value, bucket: OrdersBacklogTabsEnum::PACKED_DONE->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED_DONE->value, bucket: OrdersBacklogTabsEnum::PACKED_DONE->value))),

                OrdersBacklogTabsEnum::DISPATCHED_TODAY->value => $this->tab == OrdersBacklogTabsEnum::DISPATCHED_TODAY->value ?
                fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))
                : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))),

            ]
        )->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket:OrdersBacklogTabsEnum::IN_BASKET->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket:OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket:OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::PICKING->value, bucket:OrdersBacklogTabsEnum::PICKING->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::BLOCKED->value, bucket:OrdersBacklogTabsEnum::BLOCKED->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket:OrdersBacklogTabsEnum::PACKED->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::PACKED_DONE->value, bucket:OrdersBacklogTabsEnum::PACKED_DONE->value))
        ->table(IndexOrders::make()->tableStructure(parent:$parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket:OrdersBacklogTabsEnum::DISPATCHED_TODAY->value));
    }

    public function getBreadcrumbs(Organisation|Shop $parent, array $routeParameters): array
    {
        return match (class_basename($parent)) {
            'Shop' =>
            array_merge(
                ShowOrderingDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.ordering.backlog',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders backlog')
                        ]
                    ]
                ]
            ),
            default =>
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.ordering.backlog',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Orders backlog').' ('.__('all shops').')',
                        ]
                    ]
                ]
            )
        };
    }

}
