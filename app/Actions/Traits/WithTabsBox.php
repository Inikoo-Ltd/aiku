<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Thu, 20 Nov 2025 16:30:26 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithTabsBox
{
    public function buildWaitingItemsData(Group|Organisation|Shop $parent, ActionRequest $request): array
    {
        $query = DeliveryNote::where('state', DeliveryNoteStateEnum::HANDLING_BLOCKED)->where('number_items_waiting_crm', '>', 0);
        if ($parent instanceof Shop) {
            $query->where('shop_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('organisation_id', $parent->id);
        } else {
            $query->where('group_id', $parent->id);
        }
        $count = $query->count();

        $route = null;

        if ($parent instanceof Shop) {
            $route = [
                'name'       => 'grp.org.shops.show.ordering.backlog.waiting_items',
                'parameters' => $request->route()->originalParameters(),
            ];
        }

        return compact('count', 'route');
    }

    private function getChildRoute(Group|Organisation $parent, mixed $child, string $tabSlug): array
    {
        if ($parent instanceof Group) {
            return [
                'name'       => 'grp.org.overview.ordering.backlog',
                'parameters' => [
                    'organisation' => $child->slug,
                    'tab'          => $tabSlug,
                ],
            ];
        }

        return [
            'name'       => 'grp.org.shops.show.ordering.backlog',
            'parameters' => [
                'organisation' => $parent->slug,
                'shop'         => $child->slug,
                'tab'          => $tabSlug,
            ],
        ];
    }

    public function getTabsBox(Group|Organisation|Shop $parent, ?array $waitingItemsData = null): array
    {
        $currency     = "";
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

        $return_stat = null;
        if ($parent instanceof Shop) {
            $return_stat = $parent->number_return_delivery_notes_state_returned;
        } else {
            $return_stat = $parent->procurementStats->number_return_delivery_notes_state_returned;
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
                            'route'       => $this->getChildRoute($parent, $child, 'in_basket'),
                        ]
                    ]
                ])->values()->toArray(),
            ],
            [
                'label'         => __('Submitted'),
                'show_total'    => true,
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
                            'route'       => $this->getChildRoute($parent, $child, 'submitted_unpaid'),
                        ],
                        [
                            'tab_slug'    => 'submitted_paid',
                            'value'       => $child->orderHandlingStats?->number_orders_state_submitted_paid ?? 0,
                            'type'        => 'number',
                            'information' => [
                                'type'  => 'currency',
                                'label' => $child->orderHandlingStats?->{"orders_state_submitted_paid_amount$childCurrencySuffix"} ?? 0,
                            ],
                            'route'       => $this->getChildRoute($parent, $child, 'submitted_paid'),
                        ],
                    ]
                ])->values()->toArray(),
            ],
            [
                'label'         => __('Warehouse'),
                'show_total'    => true,
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
                        ],
                        'warning'     => ($waitingItemsData && ($waitingItemsData['count'] ?? 0) > 0) ? [
                            'route_target' => $waitingItemsData['route'] ?? null,
                            'tooltip'      => __('Orders waiting for items'),
                            'value'        => $waitingItemsData['count'],
                            'indicator'    => true,
                        ] : null,
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
                            'route'       => $this->getChildRoute($parent, $child, 'in_warehouse'),
                        ],
                        [
                            'tab_slug'    => 'handling',
                            'value'       => $child->orderHandlingStats?->number_orders_state_handling ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_handling_amount$childCurrencySuffix"} ?? 0],
                            'route'       => $this->getChildRoute($parent, $child, 'handling'),
                        ],
                        [
                            'tab_slug'    => 'handling_blocked',
                            'value'       => $child->orderHandlingStats?->number_orders_state_handling_blocked ?? 0,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_handling_blocked_amount$childCurrencySuffix"} ?? 0],
                            'route'       => $this->getChildRoute($parent, $child, 'handling_blocked'),
                        ],
                        [
                            'tab_slug'    => 'picked',
                            'value'       => $child->orderHandlingStats?->number_orders_state_picked ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_picked_amount$childCurrencySuffix"} ?? 0],
                            'route'       => $this->getChildRoute($parent, $child, 'picked'),
                        ],
                        [
                            'tab_slug'    => 'packing',
                            'value'       => $child->orderHandlingStats?->number_orders_state_packing ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_packing_amount$childCurrencySuffix"} ?? 0],
                            'route'       => $this->getChildRoute($parent, $child, 'packing'),
                        ],
                        [
                            'tab_slug'    => 'packed',
                            'value'       => $child->orderHandlingStats?->number_orders_state_packed ?? 0,
                            'information' => ['type' => 'currency', 'label' => $child->orderHandlingStats?->{"orders_state_packed_amount$childCurrencySuffix"} ?? 0],
                            'route'       => $this->getChildRoute($parent, $child, 'packed'),
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
                        'label'       => __('Waiting for dispatch'),
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
                            'route'       => $this->getChildRoute($parent, $child, 'finalised'),
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
                            'route'       => $this->getChildRoute($parent, $child, 'dispatched_today'),
                        ],
                    ]
                ])->values()->toArray(),
            ],
            [
                'label'         => __('Returns'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'returned',
                        'label'       => __('Returns'),
                        'value'       => $return_stat ?? 0,
                        'icon_data'   => [
                            'tooltip' => __('Returned'),
                            'icon'    => 'fal fa-exchange',
                            'class'   => 'text-gray-500',
                            'color'   => 'blue',
                            'app'     => [
                                'name' => 'exchange',
                                'type' => 'font-awesome-5'
                            ]
                        ],
                        'type'        => 'number',
                    ],
                ],
            ]
        ];
    }
}
