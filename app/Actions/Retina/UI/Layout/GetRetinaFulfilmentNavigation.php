<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaFulfilmentNavigation
{
    use AsAction;

    public function handle(WebUser $webUser): array
    {
        $groupNavigation = [];

        $groupNavigation['home'] = [
            'label'   => __('Home'),
            'icon'    => ['fal', 'fa-home'],
            'root'    => 'retina.dashboard.show',
            'route'   => [
                'name' => 'retina.dashboard.show'
            ],
            'topMenu' => []

        ];

        if ($webUser->customer->status === CustomerStatusEnum::APPROVED && $webUser->customer->fulfilmentCustomer->rentalAgreement) {
            $additionalSubsections = [];

            if ($webUser?->customer?->fulfilmentCustomer?->number_pallets_status_storing) {
                $additionalSubsections = [
                    [
                        'label' => __('goods out'),
                        'icon'  => ['fal', 'fa-truck-ramp'],
                        'root'  => 'retina.fulfilment.storage.pallet_returns.',
                        'route' => [
                            'name' => 'retina.fulfilment.storage.pallet_returns.index'
                        ]
                    ]
                ];
            }


            $groupNavigation['storage'] = [
                'label'   => __('Storage'),
                'icon'    => ['fal', 'fa-pallet'],
                'root'    => 'retina.fulfilment.storage.',
                'route'   => [
                    'name' => 'retina.fulfilment.storage.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('goods'),
                            'icon'  => ['fal', 'fa-pallet'],
                            'root'  => 'retina.fulfilment.storage.pallets.',
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallets.storing_pallets.index'
                            ]
                        ],
                        [
                            'label' => __('goods In'),
                            'icon'  => ['fal', 'fa-truck'],
                            'root'  => 'retina.fulfilment.storage.pallet_deliveries.',
                            'route' => [
                                'name' => 'retina.fulfilment.storage.pallet_deliveries.index'
                            ]
                        ],
                        ...$additionalSubsections,


                    ]
                ]
            ];

            if ($webUser->customer->fulfilmentCustomer->items_storage) {
                $groupNavigation['stored_items'] = [
                    'label'   => __('Skus'),
                    'icon'    => ['fal', 'fa-barcode'],
                    'root'    => 'retina.fulfilment.itemised_storage.',
                    'route'   => [
                        'name' => 'retina.fulfilment.itemised_storage.stored_items.index'
                    ],
                    'topMenu' => [
                        'subSections' => [

                            [
                                'label' => __('SKUs'),
                                'icon'  => ['fal', 'fa-barcode'],
                                'root'  => 'retina.fulfilment.itemised_storage.stored_items.',
                                'route' => [
                                    'name' => 'retina.fulfilment.itemised_storage.stored_items.index'
                                ]
                            ],
                            [
                                'label' => __('Audits'),
                                'icon'  => ['fal', 'fa-ballot-check'],
                                'root'  => 'retina.fulfilment.itemised_storage.stored_items_audits.index',
                                'route' => [
                                    'name' => 'retina.fulfilment.itemised_storage.stored_items_audits.index'
                                ]
                            ]

                        ]
                    ]
                ];

                $groupNavigation['platform'] = [
                    'label'         => __('Channels'),
                    'icon'          => 'fal fa-code-branch',
                    'icon_rotation' => 90,
                    'root'          => 'retina.dropshipping.platform.',
                    'route'         => [
                        'name' => 'retina.dropshipping.platform.dashboard'
                    ]
                ];

                $platforms_navigation = [];

                /** @var Platform $platform */
                foreach (
                    $webUser->customer->platforms()->get() as $platform
                ) {
                    $platforms_navigation[] = [
                        'type'          => $platform->type,
                        'slug'          => $platform->slug,
                        'label'         => $platform->name,
                        'root'          => 'retina.dropshipping.platforms.',
                        'subNavigation' => GetRetinaFulfilmentPlatformNavigation::run($webUser, $platform)
                    ];
                }

                if (!blank($platforms_navigation)) {
                    $groupNavigation['platforms_navigation'] = [
                        'platforms_navigation' => [
                            'label'      => __('platforms'),
                            'icon'       => "fal fa-store-alt",
                            'navigation' => array_reverse($platforms_navigation)
                        ],
                    ];
                }
            }


            $groupNavigation['spaces'] = [
                'label'   => __('Spaces'),
                'icon'    => ['fal', 'fa-parking'],
                'root'    => 'retina.fulfilment.spaces.',
                'route'   => [
                    'name' => 'retina.fulfilment.spaces.index'
                ],
                'topMenu' => []
            ];


            $currentRecurringBill = $webUser->customer?->fulfilmentCustomer?->currentRecurringBill;

            $groupNavigation['billing'] = [
                'label'   => __('billing'),
                'icon'    => ['fal', 'fa-file-invoice-dollar'],
                'root'    => 'retina.fulfilment.billing.',
                'route'   => [
                    'name' => 'retina.fulfilment.billing.dashboard'
                ],
                'topMenu' => [
                    'subSections' => [
                        $currentRecurringBill ? [
                            'label' => __('next bill'),
                            'icon'  => ['fal', 'fa-receipt'],
                            'root'  => 'retina.fulfilment.billing.next_recurring_bill',
                            'route' => [
                                'name' => 'retina.fulfilment.billing.next_recurring_bill'
                            ]
                        ] : null,

                        [
                            'label' => __('invoices'),
                            'icon'  => ['fal', 'fa-file-invoice-dollar'],
                            'root'  => 'retina.fulfilment.billing.invoices.',
                            'route' => [
                                'name' => 'retina.fulfilment.billing.invoices.index',

                            ]
                        ],
                    ]
                ]
            ];


            if ($webUser->is_root) {
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
            }
        }

        return $groupNavigation;
    }
}
