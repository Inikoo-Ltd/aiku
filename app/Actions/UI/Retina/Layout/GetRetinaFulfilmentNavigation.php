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
            'root'    => 'retina.dashboard.show',
            'route'   => [
                'name' => 'retina.dashboard.show'
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

        $groupNavigation['storage'] = [
            'label'   => __('Storage'),
            'icon'    => ['fal', 'fa-pallet'],
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
