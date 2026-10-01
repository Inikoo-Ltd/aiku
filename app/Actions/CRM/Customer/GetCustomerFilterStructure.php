<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Comms\Mailshot\Filters\FilterTopCustomersByRevenue;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCustomerFilterStructure
{
    use AsObject;

    public function handle(Shop $shop): array
    {
        $interestTags = Tag::query()
            ->where(function ($query) use ($shop) {
                $query->where('scope', TagScopeEnum::SYSTEM_CUSTOMER)
                    ->orWhere(function ($query) use ($shop) {
                        $query->whereIn('scope', [TagScopeEnum::ADMIN_CUSTOMER, TagScopeEnum::USER_CUSTOMER])
                            ->where('shop_id', $shop->id);
                    });
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => ['value' => $tag->id, 'label' => $tag->name])
            ->toArray();

        $countries = Country::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($country) => [
                'value' => $country->id,
                'label' => $country->name,
            ])
            ->toArray();

        $currencySymbol = $shop->currency->symbol ?? '£';

        return [
            'marketing' => [
                'title'   => 'Email Marketing Targeting',
                'filters' => [
                    'registered_never_ordered' => [
                        'label'       => 'Registered Never Ordered',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have created an account but have never placed an order. Baskets and cancelled orders do not count as orders. The optional date range filters on the registration date.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Registration Date Range',
                                'placeholder' => 'Select date range'
                            ]
                        ]
                    ],
                    'by_family'              => [
                        'label'       => 'By Family',
                        'type'        => 'entity_behaviour',
                        'description' => 'Targets customers who have purchased, favourited, or left in their basket without purchasing products from the selected family.',

                        'fields'      => [
                            'content' => [
                                'type'        => 'multiselect',
                                'label'       => 'By Family',
                                'placeholder' => 'Select',
                                'multiple'    => false,
                                'options'     => [],
                            ],
                            'behaviours'   => [
                                'type'    => 'select',
                                'label'   => 'Radius',
                                'options' => [
                                    ['value' => 'purchased', 'label' => 'Purchased'],
                                    ['value' => 'favourited', 'label' => 'Favourited'],
                                    ['value' => 'basket_not_purchased', 'label' => 'In basket but not purchased'],
                                ]
                            ]
                        ]
                    ],
                    'by_family_never_ordered' => [
                        'label'       => 'By Family Never Ordered',
                        'type'        => 'multiselect',
                        'description' => 'Targets customers who have never placed an order containing products from the selected family.',
                        'multiple'    => false,
                        'options'     => [],
                    ],
                    'orders_in_basket' => [
                        'label'       => 'Orders In Basket',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who currently have an order in their basket, optionally by when the basket was last updated and by its net value.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Basket Age',
                                'placeholder' => 'Select time frame (e.g. last 7 days)',
                            ],
                            'amount_range' => [
                                'type'        => 'number_range',
                                'label'       => 'Basket Value Range (Net Amount)',
                                'min_label'   => 'Min Value',
                                'max_label'   => 'Max Value',
                                'currency'    => $currencySymbol,
                            ]
                        ]
                    ],
                    'by_order_value' => [
                        'label'       => 'By Order Value',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have placed at least one order whose net value is within the range.',
                        'options'     => [
                            'amount_range' => [
                                'type'        => 'number_range',
                                'label'       => 'Order Value Range',
                                'min_label'   => 'Min Value',
                                'max_label'   => 'Max Value',
                                'currency'    => $currencySymbol,

                            ]
                        ]
                    ],
                    'ordered_in_period' => [
                        'label'       => 'Ordered In Period',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who placed at least one order (not a basket, not cancelled) within the date range. Leave the end date empty to count orders up to today, or clear the range to include anyone who has ever ordered.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Order Date Range',
                                'placeholder' => 'Select date range',
                                'hint'        => 'Pick only a start date to count orders up to today. Empty means ordered at any time.',
                            ]
                        ]
                    ],
                    'lapsed_customers' => [
                        'label'       => 'Lapsed Customers',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who ordered before the start of the date range but placed no order within it. Leave the end date empty to mean no order since the start date.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'No Orders Since',
                                'placeholder' => 'Select date range',
                                'hint'        => 'Pick only a start date: customers who ordered before it but not since.',
                                'required'    => true,
                            ]
                        ]
                    ],
                    'top_customers_by_revenue' => [
                        'label'       => 'Top Customers By Revenue',
                        'type'        => 'boolean',
                        'description' => 'Targets the customers with the highest net invoiced revenue (refunds deducted) within the date range, as a percentage of the customers who had revenue in that range. Leave the date range empty to rank by all-time revenue.',
                        'options'     => [
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Revenue Date Range',
                                'placeholder' => 'Select date range',
                            ],
                            'percentage' => [
                                'type'    => 'percentage',
                                'label'   => 'Top Percentage',
                                'default' => FilterTopCustomersByRevenue::DEFAULT_PERCENTAGE,
                            ]
                        ]
                    ],
                    'due_to_reorder' => [
                        'label'       => 'Due to Reorder',
                        'type'        => 'boolean',
                        'description' => 'Targets repeat customers whose estimated next order, from their average time between orders, is due within a week or has passed by less than one of their usual intervals, and who have no order in progress.',
                        'options'     => [],
                    ],
                    'orders_collection' => [
                        'label'       => 'Orders Collection',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have ever selected "Collection" and collected an order from the warehouse.',
                        'options'     => [],
                    ],
                    'by_subdepartment' => [
                        'label'       => 'By Subdepartment',
                        'type'        => 'entity_behaviour',
                        'description' => 'Targets customers who have purchased, or have in their basket, products from the selected sub-departments.',
                        'fields'      => [
                            'content' => [
                                'type'        => 'multiselect',
                                'label'       => 'By Subdepartment',
                                'placeholder' => 'Select',
                                'multiple'    => true,
                                'options'     => [],
                            ],
                            'behaviours'   => [
                                'type'    => 'select',
                                'label'   => 'Customer Behaviour',
                                'options' => [
                                    ['value' => 'purchased', 'label' => 'Purchased products in the past'],
                                    ['value' => 'in_basket', 'label' => 'Added to basket (not completed)'],
                                ]
                            ],
                        ]
                    ],
                    'gold_reward_status' => [
                        'label'       => 'Gold Reward Membership',
                        'type'        => 'select',
                        'description' => 'Gold Reward members are customers whose last invoice is within the last 30 days; non-members were last invoiced more than 30 days ago.',
                        'multiple'    => false,
                        'options'     => [
                            ['value' => 'gold', 'label' => 'Gold Reward Members (Active < 30 days)'],
                            ['value' => 'non_gold', 'label' => 'Non-Gold Reward Members (Inactive > 30 days)'],
                        ],
                    ],
                    'by_interest' => [
                        'label'          => 'By Tags',
                        'type'           => 'multiselect',
                        'description'    => 'Targets customers who have selected at least one of the chosen tags in their profile.',
                        'options'        => $interestTags,
                        'multiple'       => true,
                        'logic'          => 'OR'
                    ],
                    'by_showroom_orders'       => [
                        'label'       => 'By Showroom Orders',
                        'type'        => 'boolean',
                        'description' => 'Targets customers who have placed at least one order in the showroom in the past.'
                    ],
                    'by_location'              => [
                        'label'       => 'By Location',
                        'type'        => 'location',
                        'description' => 'Targets customers by country and postcode, or by distance from a location.',
                        'fields'      => [

                            'mode' => [
                                'type'    => 'select',
                                'label'   => 'Filter Mode',
                                'default' => 'direct',
                                'options' => [
                                    'direct' => 'By Country & Postcode',
                                    'radius' => 'By Radius (Geocoding)',
                                ]
                            ],

                            'country_ids' => [
                                'type'        => 'multiselect',
                                'label'       => 'Countries',
                                'placeholder' => 'Select countries',
                                'options'     => $countries,
                                'dependency'  => ['mode' => 'direct']
                            ],

                            'postal_codes' => [
                                'type'        => 'tags',
                                'label'       => 'Postal Codes',
                                'placeholder' => 'Type postcode',
                                'dependency'  => ['mode' => 'direct']
                            ],

                            'location' => [
                                'type'        => 'input',
                                'label'       => 'Center Location (Address/City)',
                                'placeholder' => 'e.g. London, United Kingdom',
                                'dependency'  => ['mode' => 'radius']
                            ],

                            'radius'   => [
                                'type'    => 'select',
                                'label'   => 'Radius Distance',
                                'options' => [
                                    '5'    => '5 KM',
                                    '10'   => '10 KM',
                                    '25'   => '25 KM',
                                    '50'   => '50 KM',
                                    '100'  => '100 KM',
                                    'custom' => 'Custom',
                                ],
                                'dependency' => ['mode' => 'radius']
                            ]
                        ]
                    ],

                    'by_departments'              => [
                        'label'       => 'By Departments',
                        'type'        => 'entity_behaviour',
                        'description' => 'Targets customers who have ordered, or have in their basket, products from the selected departments, optionally within an order date range.',
                        'fields'      => [
                            'content' => [
                                'type'        => 'multiselect',
                                'label'       => 'By Department',
                                'placeholder' => 'Select',
                                'multiple'    => true,
                                'options'     => [],
                            ],
                            'behaviours'   => [
                                'type'    => 'select',
                                'label'   => 'Customer Behaviour',
                                'options' => [
                                    ['value' => 'purchased', 'label' => 'Purchased'],
                                    ['value' => 'basket_not_purchased', 'label' => 'In basket but not purchased'],
                                ]
                            ],
                            'date_range' => [
                                'type'        => 'daterange',
                                'label'       => 'Order Date Range (optional)',
                                'placeholder' => 'Any time',
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }
}
