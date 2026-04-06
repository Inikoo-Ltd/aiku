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
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithTabsBox
{
    public function getStockHistoryTabsBox(Group $group): array
    {
        $group->loadMissing(['organisations' => fn ($q) => $q->where('type', OrganisationTypeEnum::SHOP)->with(['currency'])]);
        $ecommerceOrgs = $group->organisations->where('type', OrganisationTypeEnum::SHOP)->values();

        $orgHistories = collect();
        foreach ($ecommerceOrgs as $org) {
            $history = OrganisationStockHistory::query()
                ->where('organisation_id', $org->id)
                ->where('is_week', false)
                ->where('is_month', false)
                ->where('is_year', false)
                ->latest('date')
                ->first();
            if ($history) {
                $orgHistories->push(['org' => $org, 'history' => $history]);
            }
        }

        $totalSkus        = $orgHistories->sum(fn ($item) => $item['history']->number_org_stocks);
        $totalOutOfStock  = $orgHistories->sum(fn ($item) => $item['history']->number_out_of_stock_org_stocks);
        $totalLocations   = $orgHistories->sum(fn ($item) => $item['history']->number_locations);
        $totalNotSold1y   = $orgHistories->sum(fn ($item) => $item['history']->number_org_stocks_not_sold_1y);
        $totalStockValue  = $orgHistories->sum(fn ($item) => (float) $item['history']->grp_stock_value);
        $totalDormant1y   = $orgHistories->sum(fn ($item) => (float) $item['history']->value_dormant_stock_1y);

        $pctOutOfStock = $totalSkus > 0 ? round($totalOutOfStock / $totalSkus * 100, 1) : 0;
        $pctDormant1y  = $totalStockValue > 0 ? round($totalDormant1y / $totalStockValue * 100, 1) : 0;
        $pctNotSold1y  = $totalSkus > 0 ? round($totalNotSold1y / $totalSkus * 100, 1) : 0;

        $currencyCode = $group->currency->code;

        return [
            [
                'label'         => __('Total SKUs'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'total_skus',
                        'label'       => __('Total SKUs'),
                        'value'       => $totalSkus,
                        'type'        => 'number',
                        'icon_data'   => ['icon' => 'fal fa-box', 'tooltip' => __('Total SKUs')],
                        'information' => ['type' => 'currency', 'label' => $totalStockValue],
                    ],
                ],
                'children'      => $orgHistories->map(fn ($item) => [
                    'label'         => $item['org']->name,
                    'slug'          => $item['org']->slug,
                    'currency_code' => $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'total_skus',
                            'value'       => $item['history']->number_org_stocks,
                            'type'        => 'number',
                            'information' => ['type' => 'currency', 'label' => (float) $item['history']->grp_stock_value],
                        ],
                    ],
                ])->values()->toArray(),
            ],
            [
                'label' => __('Locations'),
                'tabs'  => [
                    [
                        'tab_slug'  => 'total_locations',
                        'label'     => __('Locations'),
                        'value'     => $totalLocations,
                        'type'      => 'number',
                        'icon_data' => ['icon' => 'fal fa-inventory', 'tooltip' => __('Locations')],
                    ],
                ],
                'children' => $orgHistories->map(fn ($item) => [
                    'label' => $item['org']->name,
                    'slug'  => $item['org']->slug,
                    'tabs'  => [
                        [
                            'tab_slug' => 'total_locations',
                            'value'    => $item['history']->number_locations,
                            'type'     => 'number',
                        ],
                    ],
                ])->values()->toArray(),
            ],
            [
                'label' => __('Out of Stock'),
                'tabs'  => [
                    [
                        'tab_slug'    => 'out_of_stock',
                        'label'       => __('Out of Stock'),
                        'value'       => $totalOutOfStock,
                        'type'        => 'number',
                        'icon_data'   => ['icon' => 'fas fa-times-circle', 'tooltip' => __('Out of Stock'), 'class' => 'text-red-500'],
                        'information' => ['label' => $pctOutOfStock . '%'],
                    ],
                ],
                'children' => $orgHistories->map(fn ($item) => [
                    'label' => $item['org']->name,
                    'slug'  => $item['org']->slug,
                    'tabs'  => [
                        [
                            'tab_slug'    => 'out_of_stock',
                            'value'       => $item['history']->number_out_of_stock_org_stocks,
                            'type'        => 'number',
                            'information' => [
                                'label' => ($item['history']->number_org_stocks > 0
                                    ? round($item['history']->number_out_of_stock_org_stocks / $item['history']->number_org_stocks * 100, 1)
                                    : 0) . '%',
                            ],
                        ],
                    ],
                ])->values()->toArray(),
            ],
            [
                'label'         => __('Dormant Stock (1y)'),
                'currency_code' => $currencyCode,
                'tabs'          => [
                    [
                        'tab_slug'    => 'dormant_stock_1y',
                        'label'       => __('Dormant Stock'),
                        'value'       => $totalDormant1y,
                        'type'        => 'currency',
                        'icon_data'   => ['icon' => 'fal fa-skull-cow', 'tooltip' => __('Dormant Stock 1y'), 'class' => 'text-red-500'],
                        'information' => ['label' => $pctDormant1y . '%'],
                    ],
                ],
                'children'      => $orgHistories->map(fn ($item) => [
                    'label'         => $item['org']->name,
                    'slug'          => $item['org']->slug,
                    'currency_code' => $currencyCode,
                    'tabs'          => [
                        [
                            'tab_slug'    => 'dormant_stock_1y',
                            'value'       => (float) $item['history']->value_dormant_stock_1y,
                            'type'        => 'currency',
                            'information' => [
                                'label' => ((float) $item['history']->grp_stock_value > 0
                                    ? round((float) $item['history']->value_dormant_stock_1y / (float) $item['history']->grp_stock_value * 100, 1)
                                    : 0) . '%',
                            ],
                        ],
                    ],
                ])->values()->toArray(),
            ],
            [
                'label' => __('Not Sold (1y)'),
                'tabs'  => [
                    [
                        'tab_slug'    => 'not_sold_1y',
                        'label'       => __('Not Sold 1y'),
                        'value'       => $totalNotSold1y,
                        'type'        => 'number',
                        'icon_data'   => ['icon' => 'fal fa-ban', 'tooltip' => __('Not Sold 1y'), 'class' => 'text-red-500'],
                        'information' => ['label' => $pctNotSold1y . '%'],
                    ],
                ],
                'children' => $orgHistories->map(fn ($item) => [
                    'label' => $item['org']->name,
                    'slug'  => $item['org']->slug,
                    'tabs'  => [
                        [
                            'tab_slug'    => 'not_sold_1y',
                            'value'       => $item['history']->number_org_stocks_not_sold_1y,
                            'type'        => 'number',
                            'information' => [
                                'label' => ($item['history']->number_org_stocks > 0
                                    ? round($item['history']->number_org_stocks_not_sold_1y / $item['history']->number_org_stocks * 100, 1)
                                    : 0) . '%',
                            ],
                        ],
                    ],
                ])->values()->toArray(),
            ],
        ];
    }

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
