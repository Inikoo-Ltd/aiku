<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 16:30:26 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithTabsBox
{
    public function getTabsBox(Group|Organisation|Shop $parent): array
    {
        $currency = "";
        $currencyCode = $parent->currency->code;

        if ($parent instanceof Group) {
            $currency = "_grp_currency";
        }

        if ($parent instanceof Organisation) {
            $currency = "_org_currency";
        }

        return [
            [
                'label'         => __('In Basket'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_basket',
                        'label'       => __('In basket'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_creating ?? 0,
                        'type'        => 'number',
                        'icon_data'        => [
                            'icon'    => 'fal fa-shopping-basket',
                            'tooltip' => __('In Basket'),
                        ],
                        'information' => [
                            'type'  => 'currency',
                            'label' => $parent->orderHandlingStats?->{"orders_state_creating_amount$currency"} ?? 0,
                        ]
                    ]
                ]
            ],
            [
                'label'         => __('Submitted'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'submitted_unpaid',
                        'label'       => __('Submitted Unpaid'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_submitted_not_paid ?? 0,
                        'type'        => 'number',
                        'icon_data'   =>
                            [
                                'tooltip' => __('Submitted Unpaid'),
                                'icon'    => 'fal fa-circle',
                                'class'   => 'text-gray-500',
                                'color'   => 'gray',
                                'app'     => [
                                    'name' => 'circle',
                                    'type' => 'font-awesome-5'
                                ]
                            ],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_submitted_not_paid_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'submitted_paid',
                        'label'       => __('Submitted Paid'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_submitted_paid ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('Submitted Paid'),
                            'icon'    => 'fal fa-check-circle',
                            'class'   => 'text-green-600',
                            'color'   => 'lime',
                            'app'     => [
                                'name' => 'check-circle',
                                'type' => 'font-awesome-5'
                            ]
                        ],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_submitted_paid_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ],
            [
                'label'         => __('Warehouse'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'in_warehouse',
                        'label'       => __('Ready to be picked'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_in_warehouse ?? 0,
                        'type'        => 'number',
                        'icon_data'   => [
                            'tooltip' => __('To do'),
                            'icon'    => 'fal fa-clock',
                        ],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_in_warehouse_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling',
                        'label'       => __('Picking'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_handling ?? 0,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_handling_amount$currency"} ?? 0,
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'handling_blocked',
                        'label'       => __('Waiting'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_handling_blocked ?? 0,
                        'type'        => 'number',
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::HANDLING_BLOCKED->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_handling_blocked_amount$currency"} ?? 0,
                            'type'  => 'currency',
                        ]
                    ],
                    [
                        'tab_slug'    => 'picked',
                        'label'       => __('Picked'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_picked ?? 0,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PICKED->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_picked_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'packing',
                        'label'       => __('Packing'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_packing ?? 0,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PACKING->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_packing_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                    [
                        'tab_slug'    => 'packed',
                        'label'       => __('Packed'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_packed ?? 0,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::PACKED->value],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_packed_amount$currency"} ?? 0,
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
                        'label'       => __('Invoiced'),
                        'value'       => $parent->orderHandlingStats?->number_orders_state_finalised ?? 0,
                        'icon_data'   => [
                            'icon'    => 'fal fa-box-check',
                            'tooltip' => __('Finalised'),
                        ],
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_state_finalised_amount$currency"} ?? 0,
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
                        'value'       => $parent->orderHandlingStats?->number_orders_dispatched_today ?? 0,
                        'icon_data'   => OrderStateEnum::stateIcon()[OrderStateEnum::DISPATCHED->value],
                        'type'        => 'number',
                        'information' => [
                            'label' => $parent->orderHandlingStats?->{"orders_dispatched_today_amount$currency"} ?? 0,
                            'type'  => 'currency'
                        ]
                    ],
                ]
            ]
        ];
    }
}
