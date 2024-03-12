<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Retina\Layout;

use App\Models\CRM\WebUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaFulfilmentNavigation
{
    use AsAction;

    public function handle(WebUser $webUser): array
    {
        $groupNavigation = [];

        $groupNavigation['dashboard'] = [
            'label'   => __('Dashboard'),
            'icon'    => ['fal', 'fa-tachometer-alt'],
            'root'    => 'retina.dashboard.',
            'route'   => [
                'name' => 'retina.dashboard.show'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['storage'] = [
            'label'   => __('Storage'),
            'icon'    => ['fal', 'fa-pallet'],
            'root'    => 'retina.storage.',
            'route'   => [
                'name' => 'retina.storage.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('pallets'),
                        'icon'  => ['fal', 'fa-pallet'],
                        'root'  => 'retina.storage.pallets.',
                        'route' => [
                            'name'       => 'retina.storage.pallets.index'
                        ]
                    ],
                    [
                        'label' => __('pallet deliveries'),
                        'icon'  => ['fal', 'fa-truck'],
                        'root'  => 'retina.storage.pallet-deliveries.',
                        'route' => [
                            'name'       => 'retina.storage.pallet-deliveries.index'
                        ]
                    ],
                    [
                        'label' => __('pallet return'),
                        'icon'  => ['fal', 'fa-truck-ramp'],
                        'root'  => 'retina.storage.pallet-returns.',
                        'route' => [
                            'name'       => 'retina.storage.pallet-returns.index'
                        ]
                    ],
                    [
                        'label' => __('stored item return'),
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'root'  => 'retina.storage.stored-item-returns.',
                        'route' => [
                            'name'       => 'retina.storage.stored-item-returns.index'
                        ]
                    ],
                ]
            ]
        ];
        $groupNavigation['dropshipping'] = [
            'label'   => __('Dropshipping'),
            'icon'    => ['fal', 'fa-hand-holding-box'],
            'route'   => [
                'name' => 'retina.sysadmin.dashboard'
            ],
            'topMenu' => [
                'subSections' => [
                    [
                        'label' => __('users'),
                        'icon'  => ['fal', 'fa-terminal'],
                        'root'  => 'retina.sysadmin.users.',
                        'route' => [
                            'name' => 'retina.sysadmin.web-users.index',

                        ]
                    ],

                    [
                        'label' => __('system settings'),
                        'icon'  => ['fal', 'fa-cog'],
                        'root'  => 'retina.sysadmin.settings.',
                        'route' => [
                            'name' => 'retina.sysadmin.settings.edit',

                        ]
                    ],
                ]
            ]
        ];

        if ($webUser->is_root) {

            $groupNavigation['billing'] = [
                'label'   => __('billing'),
                'icon'    => ['fal', 'fa-file-invoice-dollar'],
                'route'   => [
                    'name' => 'retina.sysadmin.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('users'),
                            'icon'  => ['fal', 'fa-terminal'],
                            'root'  => 'retina.sysadmin.users.',
                            'route' => [
                                'name' => 'retina.sysadmin.web-users.index',

                            ]
                        ],

                        [
                            'label' => __('system settings'),
                            'icon'  => ['fal', 'fa-cog'],
                            'root'  => 'retina.sysadmin.settings.',
                            'route' => [
                                'name' => 'retina.sysadmin.settings.edit',

                            ]
                        ],
                    ]
                ]
            ];

            $groupNavigation['sysadmin'] = [
                'label'   => __('manage account'),
                'icon'    => ['fal', 'fa-users-cog'],
                'route'   => [
                    'name' => 'retina.sysadmin.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('users'),
                            'icon'  => ['fal', 'fa-terminal'],
                            'root'  => 'retina.sysadmin.users.',
                            'route' => [
                                'name' => 'retina.sysadmin.web-users.index',

                            ]
                        ],

                        [
                            'label' => __('system settings'),
                            'icon'  => ['fal', 'fa-cog'],
                            'root'  => 'retina.sysadmin.settings.',
                            'route' => [
                                'name' => 'retina.sysadmin.settings.edit',

                            ]
                        ],
                    ]
                ]
            ];

        }


        return $groupNavigation;
    }
}
