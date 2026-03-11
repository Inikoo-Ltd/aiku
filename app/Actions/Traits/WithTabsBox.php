<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 16:30:26 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
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
            $parent->loadMissing(['organisations' => fn ($q) => $q->where('type', OrganisationTypeEnum::SHOP)->with(['orderHandlingStats', 'currency'])]);
            $children            = $parent->organisations->where('type', OrganisationTypeEnum::SHOP)->values();
            $childCurrencySuffix = "_org_currency";
        } elseif ($parent instanceof Organisation) {
            $currency = "_org_currency";
            $parent->loadMissing(['shops' => fn ($q) => $q->where('state', ShopStateEnum::OPEN)->with(['orderHandlingStats', 'currency'])]);
            $children            = $parent->shops->where('state', ShopStateEnum::OPEN)->values();
            $childCurrencySuffix = "";
        } else {
            $children            = collect();
            $childCurrencySuffix = "";
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
                        'icon_data'   => [
                            'icon'    => 'fal fa-shopping-basket',
                            'tooltip' => __('In Basket'),
                        ],
                        'information' => [
                            'type'  => 'currency',
                            'label' => $parent->orderHandlingStats?->{"orders_state_creating_amount$currency"} ?? 0,
                        ]
                    ]
                ],
                'children'      => $children->map(fn ($child) => [
                    'label'         => $child->name,
                    'slug'          => $child->slug,
                    'currency_code' => $child->currency?->code ?? $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'in_basket',
                            'value'       => $child->orderHandlingStats?->number_orders_state_creating ?? 0,
                            'type'        => 'number',
                            'information' => [
                                'type'  => 'currency',
                                'label' => $child->orderHandlingStats?->{"orders_state_creating_amount$childCurrencySuffix"} ?? 0,
                            ],
                        ]
                    ]
                ])->values()->toArray(),
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
                        'icon_data'   => [
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
                ],
                'children'      => $children->map(fn ($child) => [
                    'label'         => $child->name,
                    'slug'          => $child->slug,
                    'currency_code' => $child->currency?->code ?? $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'submitted_unpaid',
                            'value'       => $child->orderHandlingStats?->number_orders_state_submitted_not_paid ?? 0,
                            'type'        => 'number',
                            'information' => [
                                'type'  => 'currency',
                                'label' => $child->orderHandlingStats?->{"orders_state_submitted_not_paid_amount$childCurrencySuffix"} ?? 0,
                            ],
                        ],
                        [
                            'tab_slug'    => 'submitted_paid',
                            'value'       => $child->orderHandlingStats?->number_orders_state_submitted_paid ?? 0,
                            'type'        => 'number',
                            'information' => [
                                'type'  => 'currency',
                                'label' => $child->orderHandlingStats?->{"orders_state_submitted_paid_amount$childCurrencySuffix"} ?? 0,
                            ],
                        ],
                    ]
                ])->values()->toArray(),
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
                ],
                'children'      => $children->map(fn ($child) => [
                    'label'         => $child->name,
                    'slug'          => $child->slug,
                    'currency_code' => $child->currency?->code ?? $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'in_warehouse',
                            'value'       => $child->orderHandlingStats?->number_orders_state_in_warehouse ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_in_warehouse_amount$childCurrencySuffix"} ?? 0],
                        ],
                        [
                            'tab_slug'    => 'handling',
                            'value'       => $child->orderHandlingStats?->number_orders_state_handling ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_handling_amount$childCurrencySuffix"} ?? 0],
                        ],
                        [
                            'tab_slug'    => 'handling_blocked',
                            'value'       => $child->orderHandlingStats?->number_orders_state_handling_blocked ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_handling_blocked_amount$childCurrencySuffix"} ?? 0],
                        ],
                        [
                            'tab_slug'    => 'picked',
                            'value'       => $child->orderHandlingStats?->number_orders_state_picked ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_picked_amount$childCurrencySuffix"} ?? 0],
                        ],
                        [
                            'tab_slug'    => 'packing',
                            'value'       => $child->orderHandlingStats?->number_orders_state_packing ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_packing_amount$childCurrencySuffix"} ?? 0],
                        ],
                        [
                            'tab_slug'    => 'packed',
                            'value'       => $child->orderHandlingStats?->number_orders_state_packed ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_packed_amount$childCurrencySuffix"} ?? 0],
                        ],
                    ]
                ])->values()->toArray(),
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
                ],
                'children'      => $children->map(fn ($child) => [
                    'label'         => $child->name,
                    'slug'          => $child->slug,
                    'currency_code' => $child->currency?->code ?? $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'finalised',
                            'value'       => $child->orderHandlingStats?->number_orders_state_finalised ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_finalised_amount$childCurrencySuffix"} ?? 0],
                        ],
                    ]
                ])->values()->toArray(),
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
                ],
                'children'      => $children->map(fn ($child) => [
                    'label'         => $child->name,
                    'slug'          => $child->slug,
                    'currency_code' => $child->currency?->code ?? $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'dispatched_today',
                            'value'       => $child->orderHandlingStats?->number_orders_dispatched_today ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_dispatched_today_amount$childCurrencySuffix"} ?? 0],
                        ],
                    ]
                ])->values()->toArray(),
            ]
        ];
    }
}
