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
            ]

        ];

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

        if ($user->hasPermissionTo('group-overview')) {
            $groupNavigation['overview'] = $this->getOverviewNavs();
        }

        if ($user->hasPermissionTo('sysadmin.view')) {
            $groupNavigation['sysadmin'] = $this->getSysAdminNavs();
        }

        return $groupNavigation;
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
                        'label' => __('agents'),
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'root'  => 'grp.supply-chain.agents.',
                        'route' => [
                            'name' => 'grp.supply-chain.agents.index',

                        ]
                    ],
                    [
                        'label' => __('suppliers'),
                        'icon'  => ['fal', 'fa-person-dolly'],
                        'root'  => 'grp.supply-chain.suppliers.',
                        'route' => [
                            'name' => 'grp.supply-chain.suppliers.index',

                        ]
                    ],
                    [
                        'label' => __('supplier products'),
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

    private function getOverviewNavs(): array
    {
        return [
            'label'   => __('Overview'),
            'icon'    => ['fal', 'fa-mountains'],
            'root'    => 'grp.overview.',
            'route'   => [
                'name' => 'grp.overview.hub'
            ],
            'topMenu' => []
        ];
    }

    private function getSysAdminNavs(): array
    {
        return [
            'label'   => __('sysadmin'),
            'icon'    => ['fal', 'fa-users-cog'],
            'root'    => 'grp.sysadmin.',
            'route'   => [
                'name' => 'grp.sysadmin.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('users'),
                        'icon'  => ['fal', 'fa-user-circle'],
                        'root'  => 'grp.sysadmin.users.',
                        'route' => [
                            'name' => 'grp.sysadmin.users.index',

                        ]
                    ],
                    [
                        'label' => __('guests'),
                        'icon'  => ['fal', 'fa-user-alien'],
                        'root'  => 'grp.sysadmin.guests.',
                        'route' => [
                            'name' => 'grp.sysadmin.guests.index',

                        ]
                    ],
                    [
                        'label' => __('analytics'),
                        'icon'  => ['fal', 'fa-analytics'],
                        'root'  => 'grp.sysadmin.analytics.',
                        'route' => [
                            'name' => 'grp.sysadmin.analytics.dashboard',

                        ]
                    ],
                    [
                        'label' => __('system settings'),
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
                        'label' => __('SKUs families'),
                        'icon'  => ['fal', 'fa-boxes-alt'],
                        'root'  => 'grp.goods.stock-families.',
                        'route' => [
                            'name'       => 'grp.goods.stock-families.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => 'SKUs',
                        'icon'  => ['fal', 'fa-box'],
                        'root'  => 'grp.goods.stocks.',
                        'route' => [
                            'name'       => 'grp.goods.stocks.active_stocks.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => 'Trade Units',
                        'icon'  => ['fal', 'fa-atom'],
                        'root'  => 'grp.goods.trade-units.',
                        'route' => [
                            'name'       => 'grp.goods.trade-units.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => 'Ingredients',
                        'icon'  => ['fal', 'fa-atom'],
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

    private function getMastersNavs(): array
    {
        return [
            'label'   => __('Masters'),
            'icon'    => ['fal', 'fa-ruler-triangle'],
            'root'    => 'grp.masters.',
            'route'   => [
                'name' => 'grp.masters.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('Shops'),
                        'tooltip' => __('Master shops'),
                        'icon'  => ['fal', 'fa-store-alt'],
                        'root'  => 'grp.masters.shops.',
                        'route' => [
                            'name'       => 'grp.masters.shops.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => __('Departments'),
                        'tooltip' => __('Master departments'),
                        'icon'  => ['fal', 'fa-folder-tree'],
                        'root'  => 'grp.masters.departments.',
                        'route' => [
                            'name'       => 'grp.masters.departments.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => __('Families'),
                        'tooltip' => __('Master families'),
                        'icon'  => ['fal', 'fa-folder'],
                        'root'  => 'grp.masters.families.',
                        'route' => [
                            'name'       => 'grp.masters.families.index',
                            'parameters' => []

                        ]
                    ],
                    [
                        'label' => __('Products'),
                        'tooltip' => __('Master products'),
                        'icon'  => ['fal', 'fa-cube'],
                        'root'  => 'grp.masters.products.',
                        'route' => [
                            'name'       => 'grp.masters.products.index',
                            'parameters' => []

                        ]
                    ],


                ]
            ]

        ];
    }
}
