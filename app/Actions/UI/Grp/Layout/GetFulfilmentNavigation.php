<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFulfilmentNavigation
{
    use AsAction;

    public function handle(Fulfilment $fulfilment, User $user): array
    {
        $navigation = [];

        if ($user->authTo([
            "fulfilment-shop.$fulfilment->id.view",
            "supervisor-fulfilment-shop.".$fulfilment->id,
            "accounting.$fulfilment->organisation_id.view",
        ])) {
            $navigation['operations'] = [
                'root'  => 'grp.org.fulfilments.show.operations.',
                'label' => __('Fulfilment'),
                'icon'  => ['fal', 'fa-hand-holding-box'],

                'route' => [
                    'name'       => 'grp.org.fulfilments.show.operations.dashboard',
                    'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            "tooltip" => __("Dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            "root"    => "grp.org.fulfilments.show.operations.dashboard",
                            "route"   => [
                                "name"       => "grp.org.fulfilments.show.operations.dashboard",
                                "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            "tooltip" => __("Comms"),
                            "icon"    => ["fal", "fa-satellite-dish"],
                            "root"    => "grp.org.fulfilments.show.operations.comms.dashboard",
                            "route"   => [
                                "name"       => "grp.org.fulfilments.show.operations.comms.dashboard",
                                "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            "tooltip" => __("Payments"),
                            "icon"    => ["fal", "fa-coins"],
                            "root"    => "grp.org.fulfilments.show.operations.accounting.dashboard",
                            "route"   => [
                                "name"       => "grp.org.fulfilments.show.operations.accounting.dashboard",
                                "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],

                        [
                            'label'   => __('deliveries'),
                            'tooltip' => __('Deliveries'),
                            'icon'    => ['fal', 'fa-truck-couch'],
                            'root'    => 'grp.org.fulfilments.show.operations.pallet-deliveries.',
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallet-deliveries.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            'label'   => __('returns'),
                            'tooltip' => __('Returns'),
                            'icon'    => ['fal', 'fa-sign-out'],
                            'root'    => 'grp.org.fulfilments.show.operations.pallet-returns.',
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            'label'   => __('Next bills'),
                            'tooltip' => __('Next bills'),
                            'icon'    => ['fal', 'fa-receipt'],
                            'root'    => 'grp.org.fulfilments.show.operations.recurring_bills.',
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            'label'   => __('invoices'),
                            'tooltip' => __('Invoices'),
                            'icon'    => ['fal', 'fa-file-invoice-dollar'],
                            'root'    => 'grp.org.fulfilments.show.operations.invoices.',
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],

                    ]
                ]

            ];


            $navigation['assets'] = [
                'root'  => 'grp.org.fulfilments.show.catalogue.',
                'label' => __('Catalogue'),
                'icon'  => ['fal', 'fa-ballot'],

                'route' => [
                    'name'       => 'grp.org.fulfilments.show.catalogue.index',
                    'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('rentals'),
                            'icon'  => ['fal', 'fa-garage'],
                            'root'  => 'grp.org.fulfilments.show.catalogue.rentals.',
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.catalogue.rentals.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            'label' => __('services'),
                            'icon'  => ['fal', 'fa-concierge-bell'],
                            'root'  => 'grp.org.fulfilments.show.catalogue.services.',
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.catalogue.services.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],
                        [
                            'label' => __('goods'),
                            'icon'  => ['fal', 'fa-cube'],
                            'root'  => 'grp.org.fulfilments.show.catalogue.physical_goods.',
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                            ],
                        ],


                    ]
                ]

            ];
        }


        if ($fulfilment->shop->website
            && $user->authTo([
                "fulfilment-shop.$fulfilment->id.view",
                "supervisor-fulfilment-shop.".$fulfilment->id,
                "group-webmaster.view"
            ])) {
            $navigation['web'] = [
                'root'    => 'grp.org.fulfilments.show.web.',
                'scope'   => 'websites',
                'icon'    => ['fal', 'fa-globe'],
                'label'   => __('Website'),
                'route'   =>

                    $fulfilment->shop->website
                        ?
                        [
                            'name'       => 'grp.org.fulfilments.show.web.websites.show',
                            'parameters' => [
                                $fulfilment->organisation->slug,
                                $fulfilment->slug,
                                $fulfilment->shop->website->slug
                            ]

                        ]
                        :
                        [
                            'name'       => 'grp.org.fulfilments.show.web.websites.index',
                            'parameters' => [
                                $fulfilment->organisation->slug,
                                $fulfilment->slug,
                            ]

                        ],
                'topMenu' => [
                    'subSections' =>

                        [
                            [
                                "label"   => __("Website"),
                                "tooltip" => __("website"),
                                "icon"    => ["fal", "fa-globe"],
                                'root'    => 'grp.org.fulfilments.show.web.websites.',
                                "route"   => [
                                    "name"       => "grp.org.fulfilments.show.web.websites.show",
                                    "parameters" => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug,
                                        $fulfilment->shop->website->slug
                                    ],
                                ],
                            ],


                            [
                                'label'   => __('webpages'),
                                'tooltip' => __('Webpages'),
                                'icon'    => ['fal', 'fa-browser'],
                                'root'    => 'grp.org.fulfilments.show.web.webpages.',
                                'route'   => [
                                    'name'       => 'grp.org.fulfilments.show.web.webpages.index',
                                    'parameters' => [
                                        $fulfilment->organisation->slug,
                                        $fulfilment->slug,
                                        $fulfilment->shop->website->slug
                                    ]

                                ]
                            ],

                            [
                                "label"   => __("banners"),
                                "tooltip" => __("banners"),
                                "icon"    => ["fal", "fa-sign"],
                                'root'    => 'grp.org.fulfilments.show.web.banners.index',
                                "route"   => [
                                    "name"       => "grp.org.fulfilments.show.web.banners.index",
                                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug, $fulfilment->shop->website->slug],
                                ],
                            ],
                        ]
                ],


            ];
        }

        if ($user->authTo([
            "fulfilment-shop.$fulfilment->id.view",
            "supervisor-fulfilment-shop.".$fulfilment->id,
            "accounting.$fulfilment->organisation_id.view",
        ])) {
            $navigation['crm'] = [
                'scope' => 'shops',
                'label' => __('Customers'),
                'icon'  => ['fal', 'fa-user'],
                'root'  => 'grp.org.fulfilments.show.crm.',
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                    'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug, ['elements[status]' => 'active']]
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            'label'   => __('customers'),
                            'tooltip' => __('Customers'),
                            'icon'    => ['fal', 'fa-user'],
                            'root'    => 'grp.org.fulfilments.show.crm.customers.',
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug, ['elements[status]' => 'active']]
                            ],
                        ],
                        // Prospects for fulfilment is still not supported
                        //                        [
                        //                            'label'   => __('prospects'),
                        //                            'tooltip' => __('Prospects'),
                        //                            'icon'    => ['fal', 'fa-user-plus'],
                        //                            'root'    => 'grp.org.fulfilments.show.crm.prospects.',
                        //                            'route'   => [
                        //                                'name'       => 'grp.org.fulfilments.show.crm.prospects.index',
                        //                                'parameters' => [$fulfilment->organisation->slug, $fulfilment->slug]
                        //                            ],
                        //                        ],
                    ]
                ]

            ];
            if ($user->authTo([
                "fulfilment-shop.$fulfilment->id.view",
                "supervisor-fulfilment-shop.".$fulfilment->id
            ])) {
                $navigation['setting'] = [
                    "root"    => "grp.org.fulfilments.show.settings.",
                    "icon"    => ["fal", "fa-sliders-h"],
                    "label"   => __("Setting"),
                    "route"   => [
                        "name"       => 'grp.org.fulfilments.show.settings.edit',
                        "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                    ],
                    "topMenu" => [
                        "subSections" => [
                            [
                                "tooltip" => __("Fulfilment Setting"),
                                "icon"    => ["fal", "fa-sliders-h"],
                                'root'    => 'grp.org.fulfilments.show.settings.edit',
                                "route"   => [
                                    "name"       => 'grp.org.fulfilments.show.settings.edit',
                                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                                ],
                            ],
                            [
                                "label"   => __("outboxes"),
                                "tooltip" => __("outboxes"),
                                "icon"    => ["fal", "fa-comment-dollar"],
                                'root'    => 'grp.org.fulfilments.show.settings.outboxes.index',
                                "route"   => [
                                    "name"       => "grp.org.fulfilments.show.settings.outboxes.index",
                                    "parameters" => [$fulfilment->organisation->slug, $fulfilment->slug],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        return $navigation;
    }
}
