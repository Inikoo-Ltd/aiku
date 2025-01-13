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



        $additionalSubsections = [];

        if ($webUser?->customer?->fulfilmentCustomer?->number_pallets_status_storing) {
            $additionalSubsections = [
                [
                    'label' => __('returns'),
                    'icon'  => ['fal', 'fa-truck-ramp'],
                    'root'  => 'retina.storage.pallet-returns.',
                    'route' => [
                        'name'       => 'retina.storage.pallet-returns.index'
                    ]
                ]
            ];
        }

        $groupNavigation['storage'] = [
            'label'   => __('Dashboard'),
            'icon'    => ['fal', 'fa-tachometer-alt'],
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
                        'label' => __('deliveries'),
                        'icon'  => ['fal', 'fa-truck'],
                        'root'  => 'retina.storage.pallet-deliveries.',
                        'route' => [
                            'name'       => 'retina.storage.pallet-deliveries.index'
                        ]
                    ],
                    ...$additionalSubsections,
                    [
                        'label' => __('assets'),
                        'icon'  => ['fal', 'fa-ballot'],
                        'root'  => 'retina.storage.assets.',
                        'route' => [
                            'name'       => 'retina.storage.assets.index'
                        ]
                    ],
                    // [
                    //     'label' => __('stored item return'),
                    //     'icon'  => ['fal', 'fa-truck-couch'],
                    //     'root'  => 'retina.storage.stored-item-returns.',
                    //     'route' => [
                    //         'name'       => 'retina.storage.stored-item-returns.index'
                    //     ]
                    // ],
                ]
            ]
        ];
        /*$groupNavigation['dropshipping'] = [
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
        ];*/

        if ($webUser->is_root) {

            $groupNavigation['billing'] = [
                'label'   => __('billing'),
                'icon'    => ['fal', 'fa-file-invoice-dollar'],
                'root'    => 'retina.billing.',
                'route'   => [
                    'name' => 'retina.billing.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('next bill'),
                            'icon'  => ['fal', 'fa-receipt'],
                            'root'  => 'retina.billing.next_recurring_bill',
                            'route' => [
                                'name' => 'retina.billing.next_recurring_bill',

                            ]
                        ],

                        [
                            'label' => __('invoices'),
                            'icon'  => ['fal', 'fa-file-invoice-dollar'],
                            'root'  => 'retina.billing.invoices.',
                            'route' => [
                                'name' => 'retina.billing.invoices.index',

                            ]
                        ],
                    ]
                ]
            ];

            $groupNavigation['sysadmin'] = [
                'label'   => __('manage account'),
                'icon'    => ['fal', 'fa-users-cog'],
                'root'    => 'retina.sysadmin.',
                'route'   => [
                    'name' => 'retina.sysadmin.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('users'),
                            'icon'  => ['fal', 'fa-user-circle'],
                            'root'  => 'retina.sysadmin.web-users.',
                            'route' => [
                                'name' => 'retina.sysadmin.web-users.index',

                            ]
                        ],

                        [
                            'label' => __('account settings'),
                            'icon'  => ['fal', 'fa-cog'],
                            'root'  => 'retina.sysadmin.settings.',
                            'route' => [
                                'name' => 'retina.sysadmin.settings.edit',

                            ]
                        ],
                    ]
                ]
            ];

            /*            $groupNavigation['dropshipping'] = [
                            'label'   => __('Dropshipping'),
                            'icon'    => ['fal', 'fa-parachute-box'],
                            'root'    => 'retina.dropshipping.',
                            'route'   => [
                                'name' => 'retina.dropshipping.dashboard'
                            ],
                            'topMenu' => [
                                'subSections' => [
                                    [
                                        'label' => __('Products'),
                                        'icon'  => ['fal', 'fa-cube'],
                                        'root'  => 'retina.dropshipping.products.',
                                        'route' => [
                                            'name' => 'retina.dropshipping.products.index',

                                        ]
                                    ],
                                ]
                            ]
                        ];*/

        }


        return $groupNavigation;
    }
}
