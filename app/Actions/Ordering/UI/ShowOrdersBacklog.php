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
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
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
    use WithOrderingAuthorisation;

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

        $currencyCode = $parent->currency->code;

        $icon = 'fal fa-tachometer-alt';

        $tabsBox = [
            [
                'label'         => __('In Basket'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_basket',
                        'label'       => __('In basket'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_creating,
                        'type'        => 'number',
                        'icon'        => 'fal fa-shopping-basket',
                        'information' => [
                            'type'  => 'currency',
                            'label' => $parent->orderHandlingStats->{"orders_state_creating_amount$currency"},
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Submitted'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted_paid',
                        'label'       => __('Submitted Paid'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_submitted_paid,
                        'type'        => 'number',
                        // 'icon'        => $icon,
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::PAID->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_submitted_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'submitted_unpaid',
                        'label'       => __('Submitted Unpaid'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_submitted_not_paid,
                        'type'        => 'number',
                        // 'icon'        => $icon,
                        'icon_data'   => OrderPayStatusEnum::typeIcon()[OrderPayStatusEnum::UNPAID->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_submitted_not_paid_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Warehouse'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_warehouse',
                        'label'       => __('Waiting'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_in_warehouse,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::IN_WAREHOUSE->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_in_warehouse_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling',
                        'label'       => __('Piking'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_handling,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_handling_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling_blocked',
                        'label'       => __('Blocked'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_handling_blocked,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_handling_blocked_amount$currency"},
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'packed',
                        'label'       => __('Packed'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_packed,
                        'icon'        => 'fal fa-box',
                        'iconClass'   => 'text-teal-500',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_packed_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Waiting for dispatch'),
                'currency_code' => $currencyCode,
                'tabs'          => [

                    [
                        'tab_slug'    => 'finalised',
                        'label'       => __('Finalised'),
                        'value'       => $parent->orderHandlingStats->number_orders_state_finalised,
                        'icon'        => 'fal fa-box-check',
                        'iconClass'   => 'text-orange-500',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_state_finalised_amount$currency"},
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Dispatched Today'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'dispatched_today',
                        'label'       => __('Dispatched Today'),
                        'value'       => $parent->orderHandlingStats->number_orders_dispatched_today,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::DISPATCHED->value],
                        'type'        => 'number',
                        'information' => [
                            'label' => $parent->orderHandlingStats->{"orders_dispatched_today_amount$currency"},
                            'type'  => 'currency'
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
                'title'       => __('Orders backlog'),
                'pageHead'    => [
                    'title' => __('Orders backlog'),

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

                OrdersBacklogTabsEnum::IN_WAREHOUSE->value => $this->tab == OrdersBacklogTabsEnum::IN_WAREHOUSE->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))),


                OrdersBacklogTabsEnum::HANDLING->value => $this->tab == OrdersBacklogTabsEnum::HANDLING->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))),

                OrdersBacklogTabsEnum::HANDLING_BLOCKED->value => $this->tab == OrdersBacklogTabsEnum::HANDLING_BLOCKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))),

                OrdersBacklogTabsEnum::PACKED->value => $this->tab == OrdersBacklogTabsEnum::PACKED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))),

                OrdersBacklogTabsEnum::FINALISED->value => $this->tab == OrdersBacklogTabsEnum::FINALISED->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))),

                OrdersBacklogTabsEnum::DISPATCHED_TODAY->value => $this->tab == OrdersBacklogTabsEnum::DISPATCHED_TODAY->value ?
                    fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))
                    : Inertia::lazy(fn () => OrdersResource::collection(IndexOrders::run(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value))),

            ]
        )->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_BASKET->value, bucket: OrdersBacklogTabsEnum::IN_BASKET->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_PAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_PAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value, bucket: OrdersBacklogTabsEnum::SUBMITTED_UNPAID->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::IN_WAREHOUSE->value, bucket: OrdersBacklogTabsEnum::IN_WAREHOUSE->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING->value, bucket: OrdersBacklogTabsEnum::HANDLING->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value, bucket: OrdersBacklogTabsEnum::HANDLING_BLOCKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::PACKED->value, bucket: OrdersBacklogTabsEnum::PACKED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::FINALISED->value, bucket: OrdersBacklogTabsEnum::FINALISED->value))
            ->table(IndexOrders::make()->tableStructure(parent: $parent, prefix: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value, bucket: OrdersBacklogTabsEnum::DISPATCHED_TODAY->value));
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
