<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetGroupNavigation
{
    use AsAction;

    public function handle(User $user): array
    {
        $groupNavigation = [];

        $groupNavigation['group'] = [
            'label'   => __('Group'),
            'icon'    => ['fal', 'fa-city'],
            'root'    => 'grp.dashboard.show',
            'route'   => [
                'name' => 'grp.dashboard.show'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        "tooltip" => __("Catalogue"),
                        "icon"    => ["fal", "fa-books"],
                        'root'    => 'grp.catalogue.show',
                        "route"   => [
                            "name"       => 'grp.catalogue.show',
                            "parameters" => [],
                        ],
                    ],
                    [
                        "tooltip" => __("Platform"),
                        'icon'    => ['fal', 'fa-code-branch'],
                        'root'    => 'grp.platforms.index',
                        "route"   => [
                            "name"       => 'grp.platforms.index',
                            "parameters" => [],
                        ],
                    ]
                ],
            ]
        ];
        if ($user->hasAnyPermission(['goods.view','masters.view'])) {
            $groupNavigation['trade-units'] = $this->getTradeUnitsNavs();
        }

        if ($user->hasPermissionTo('masters.view')) {
            $groupNavigation['masters'] = $this->getMastersNavs();
        }

        if ($user->hasPermissionTo('goods.view')) {
            $groupNavigation['goods'] = $this->getMGoodsNavs();
        }

        if ($user->hasPermissionTo('supply-chain.view')) {
            $groupNavigation['supply-chain'] = $this->getSupplyChainNavs();
        }

        if ($user->hasPermissionTo('organisations.view')) {
            $groupNavigation['organisations'] = $this->getOrganisationsNavs();
        }

        if (app()->isLocal()) {
            $groupNavigation['sales-channels'] = $this->getSalesChannelsNavs();
            $groupNavigation['website'] = $this->getWebsiteNavs();
        }

        if ($user->hasPermissionTo('group-overview')) {
            $groupNavigation['overview'] = $this->getOverviewNavs();
        }


        $groupNavigation['chat'] = [
            'label'   => __('Chat'),
            'tooltip' => __('Chat'),
            'icon'    => ['fal', 'fa-comment-alt'],
            'root'    => 'grp.chat.',
            'route'   => [
                'name' => 'grp.chat.dashboard',
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label'   => __('Dashboard'),
                        'tooltip' => __('Dashboard'),
                        'icon'    => ['fal', 'fa-comment-alt'],
                        'root'    => 'grp.chat.dashboard',
                        'route'   => [
                            'name' => 'grp.chat.dashboard',
                        ],
                    ],
                    // [
                    //     'label'   => __('Agents'),
                    //     'tooltip' => __('Agents'),
                    //     'icon'    => ['fal', 'fa-headset'],
                    //     'root'    => 'grp.chat.agents.',
                    //     'route'   => [
                    //         'name' => 'grp.chat.agents.show',
                    //     ],
                    // ],
                ],
            ],
        ];


        if ($user->hasPermissionTo('sysadmin.view')) {
            $groupNavigation['sysadmin'] = $this->getSysAdminNavs();
        }

        return $groupNavigation;
    }

    private function getTradeUnitsNavs(): array
    {
        return [
            'label'   => __('Trade Units'),
            'icon'    => ['fal', 'fa-atom'],
            'root'    => 'grp.trade_units.',
            'route'   => [
                'name' => 'grp.trade_units.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('Trade Units'),
                        'icon'  => ['fal', 'fa-atom'],
                        'root'  => 'grp.trade_units.units.',
                        'route' => [
                            'name'       => 'grp.trade_units.units.active',
                            'parameters' => []
                        ]
                    ],
                    [
                        'label' => __('Trade Unit Families'),
                        'icon'  => ['fal', 'fa-atom-alt'],
                        'root'  => 'grp.trade_units.families.',
                        'route' => [
                            'name'       => 'grp.trade_units.families.index',
                            'parameters' => []
                        ]
                    ],
                    [
                        'label' => __('Brands'),
                        'icon'  => ['fal', 'fa-copyright'],
                        'root'  => 'grp.trade_units.brands.',
                        'route' => [
                            'name'       => 'grp.trade_units.brands.index',
                            'parameters' => []
                        ]
                    ],
                    [
                        'label' => __('Tags'),
                        'icon'  => ['fal', 'fa-tags'],
                        'root'  => 'grp.trade_units.tags.',
                        'route' => [
                            'name'       => 'grp.trade_units.tags.index',
                            'parameters' => []
                        ]
                    ],
                ]
            ]
        ];
    }

    private function getMastersNavs(): array
    {
        return [
            'label'   => __('Masters'),
            'icon'    => ['fab', 'fa-octopus-deploy'],
            'root'    => 'grp.masters.',
            'route'   => [
                'name' => 'grp.masters.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        "tooltip" => __("Master Catalogue"),
                        "icon"    => ["fal", "fa-books"],
                        'root'    => 'grp.masters.dashboard',
                        "route"   => [
                            "name"       => 'grp.masters.dashboard',
                            "parameters" => [],
                        ],
                    ],
                    [
                        'label' => __('Master Shops'),
                        'tooltip' => __('Master shops'),
                        'icon'  => ['fal', 'fa-store-alt'],
                        'root'  => 'grp.masters.master_shops.',
                        'route' => [
                            'name'       => 'grp.masters.master_shops.index',
                            'parameters' => []
                        ]
                    ],
                ]
            ]

        ];
    }

    private function getMGoodsNavs(): array
    {
        return [
            'label'   => __('Goods'),
            'icon'    => ['fal', 'fa-cloud-rainbow'],
            'root'    => 'grp.goods.',
            'route'   => [
                'name' => 'grp.goods.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('Master SKUs families'),
                        'icon'  => ['fal', 'fa-rainbow'],
                        'root'  => 'grp.goods.stock-families.',
                        'route' => [
                            'name'       => 'grp.goods.stock-families.index',
                            'parameters' => []
                        ]
                    ],
                    [
                        'label' => __('Master SKUs'),
                        'icon'  => ['fal', 'fa-cloud-rainbow'],
                        'root'  => 'grp.goods.stocks.',
                        'route' => [
                            'name'       => 'grp.goods.stocks.active_stocks.index',
                            'parameters' => []
                        ]
                    ],
                    [
                        'label' => __('Ingredients'),
                        'icon'  => ['fal', 'fa-apple-crate'],
                        'root'  => 'grp.goods.ingredients.',
                        'route' => [
                            'name'       => 'grp.goods.ingredients.index',
                            'parameters' => []
                        ]
                    ],
                ]
            ]

        ];
    }

    private function getSupplyChainNavs(): array
    {
        return [
            'label'   => __('Supply Chain'),
            'icon'    => ['fal', 'fa-box-usd'],
            'root'    => 'grp.supply-chain.',
            'route'   => [
                'name' => 'grp.supply-chain.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'root'  => 'grp.supply-chain.dashboard',
                        'route' => [
                            'name' => 'grp.supply-chain.dashboard',
                        ]
                    ],
                    [
                        'label' => __('Agents'),
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'root'  => 'grp.supply-chain.agents.',
                        'route' => [
                            'name' => 'grp.supply-chain.agents.index',
                        ]
                    ],
                    [
                        'label' => __('Suppliers'),
                        'icon'  => ['fal', 'fa-person-dolly'],
                        'root'  => 'grp.supply-chain.suppliers.',
                        'route' => [
                            'name' => 'grp.supply-chain.suppliers.index',
                        ]
                    ],
                    [
                        'label' => __('Supplier products'),
                        'icon'  => ['fal', 'fa-box-usd'],
                        'root'  => 'grp.supply-chain.supplier_products.',
                        'route' => [
                            'name' => 'grp.supply-chain.supplier_products.index',
                        ]
                    ],

                ]
            ]
        ];
    }

    private function getOrganisationsNavs(): array
    {
        return [
            'label'   => __('Organisations'),
            'icon'    => ['fal', 'fa-building'],
            'root'    => 'grp.organisations.',
            'route'   => [
                'name' => 'grp.organisations.index'
            ],
            'topMenu' => []
        ];
    }

    private function getSalesChannelsNavs(): array
    {
        return [
            'label'   => __('Sales Channels'),
            'icon'    => ['fal', 'fa-store'],
            'root'    => 'grp.sales_channels.',
            'route'   => [
                'name' => 'grp.sales_channels.index'
            ],
            'topMenu' => []
        ];
    }

    private function getWebsiteNavs(): array
    {
        return [
            'label'   => __('Website'),
            'icon'    => ['fal', 'fa-globe'],
            'root'    => 'grp.websites.',
            'route'   => [
                'name' => 'grp.websites.index'
            ],
            'topMenu' => []
        ];
    }

    private function getOverviewNavs(): array
    {
        return [
            'label'   => __('Overview'),
            'icon'    => ['fal', 'fa-mountains'],
            'root'    => 'grp.overview.',
            'route'   => [
                'name' => 'grp.overview.hub'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label'   => __('Top Customers'),
                        'icon'    => ['fal', 'fa-trophy'],
                        'root'    => 'grp.overview.crm.customers.top_customers',
                        'route'   => [
                            'name'       => 'grp.overview.crm.customers.top_customers',
                            'parameters' => []
                        ]
                    ],
                ]
            ]
        ];
    }

    private function getSysAdminNavs(): array
    {
        return [
            'label'   => __('Sysadmin'),
            'icon'    => ['fal', 'fa-users-cog'],
            'root'    => 'grp.sysadmin.',
            'route'   => [
                'name' => 'grp.sysadmin.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('Users'),
                        'icon'  => ['fal', 'fa-user-circle'],
                        'root'  => 'grp.sysadmin.users.',
                        'route' => [
                            'name' => 'grp.sysadmin.users.index',
                        ]
                    ],
                    [
                        'label' => __('Guests'),
                        'icon'  => ['fal', 'fa-user-alien'],
                        'root'  => 'grp.sysadmin.guests.',
                        'route' => [
                            'name' => 'grp.sysadmin.guests.index',
                        ]
                    ],
                    [
                        'label' => __('Analytics'),
                        'icon'  => ['fal', 'fa-analytics'],
                        'root'  => 'grp.sysadmin.analytics.',
                        'route' => [
                            'name' => 'grp.sysadmin.analytics.dashboard',
                        ]
                    ],
                    [
                        'label' => __('Scheduled Tasks'),
                        'icon'  => ['fal', 'fa-clock'],
                        'root'  => 'grp.sysadmin.scheduled-tasks.',
                        'route' => [
                            'name' => 'grp.sysadmin.scheduled-tasks.index',
                        ]
                    ],
                    [
                        'label' => __('System Settings'),
                        'icon'  => ['fal', 'fa-cog'],
                        'root'  => 'grp.sysadmin.settings.',
                        'route' => [
                            'name' => 'grp.sysadmin.settings.edit',
                        ]
                    ],
                ]
            ]
        ];
    }
}
