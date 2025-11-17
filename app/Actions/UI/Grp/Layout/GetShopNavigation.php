<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopNavigation
{
    use AsAction;

    public function handle(Shop $shop, User $user): array
    {
        $navigation = [];

        $navigation['dashboard'] = [
            'root'  => 'grp.org.shops.show.dashboard.',
            'label' => __('Shop'),
            'icon'  => 'fal fa-store-alt',

            'route' => [
                'name'       => 'grp.org.shops.show.dashboard.show',
                'parameters' => [$shop->organisation->slug, $shop->slug]
            ],

            'topMenu' => [
                'subSections' => [
                    [
                        "label"   => __("Comms"),
                        "tooltip" => __("Email communications"),
                        "icon"    => ["fal", "fa-satellite-dish"],
                        "route"   => [
                            "name"       => "grp.org.shops.show.dashboard.comms.dashboard",
                            "parameters" => [$shop->organisation->slug, $shop->slug],
                        ],
                    ],
                    [
                        "tooltip" => __("Payments"),
                        "label"   => __("Payments"),
                        "icon"    => ["fal", "fa-coins"],
                        "root"    => "grp.org.shops.show.dashboard.payments.accounting.dashboard",
                        "route"   => [
                            "name"       => "grp.org.shops.show.dashboard.payments.accounting.dashboard",
                            "parameters" => [$shop->organisation->slug, $shop->slug]
                        ],
                    ],
                    [
                        "label"   => __("Invoices"),
                        "tooltip" => __("Invoices"),
                        "icon"    => ["fal", "fa-file-invoice-dollar"],
                        'root'    => 'grp.org.shops.show.dashboard.invoices',
                        "route"   => [
                            "name"       => "grp.org.shops.show.dashboard.invoices.index",
                            "parameters" => [$shop->organisation->slug, $shop->slug],
                        ],
                    ],
                ],
            ]

        ];
        if ($user->hasPermissionTo("products.$shop->id.view")) {
            $navigation["catalogue"] = [
                "root"    => "grp.org.shops.show.catalogue.",
                "icon"    => ["fal", "fa-books"],
                "label"   => __("Catalogue"),
                "route"   => [
                    "name"       => 'grp.org.shops.show.catalogue.dashboard',
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "tooltip" => __("Catalogue"),
                            "icon"    => ["fal", "fa-books"],
                            'root'    => 'grp.org.shops.show.catalogue.dashboard',
                            "route"   => [
                                "name"       => 'grp.org.shops.show.catalogue.dashboard',
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Departments"),
                            "tooltip" => __("Departments"),
                            "icon"    => ["fal", "fa-folder-tree"],
                            'root'    => 'grp.org.shops.show.catalogue.departments.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.catalogue.departments.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Families"),
                            "tooltip" => __("Families"),
                            "icon"    => ["fal", "fa-folder"],
                            'root'    => 'grp.org.shops.show.catalogue.families.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.catalogue.families.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Products"),
                            "tooltip" => __("Products"),
                            "icon"    => ["fal", "fa-cube"],
                            'root'    => 'grp.org.shops.show.catalogue.products.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.catalogue.products.current_products.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Collections"),
                            "tooltip" => __("Collections"),
                            "icon"    => ["fal", "fa-album-collection"],
                            'root'    => 'grp.org.shops.show.catalogue.collections.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.catalogue.collections.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                    ],
                ],
            ];

            $navigation["billables"] = [
                "root"    => "grp.org.shops.show.billables.",
                "icon"    => ["fal", "fa-ballot"],
                "label"   => __("Billables"),
                "route"   => [
                    "name"       => 'grp.org.shops.show.billables.dashboard',
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "tooltip" => __("Shop"),
                            "icon"    => ["fal", "fa-store-alt"],
                            'root'    => 'grp.org.shops.show.billables.dashboard',
                            "route"   => [
                                "name"       => 'grp.org.shops.show.billables.dashboard',
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Shipping"),
                            "tooltip" => __("Shipping"),
                            "icon"    => ["fal", "fa-shipping-fast"],
                            'root'    => 'grp.org.shops.show.billables.shipping.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.billables.shipping.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Charges"),
                            "tooltip" => __("Charges"),
                            "icon"    => ["fal", "fa-charging-station"],
                            'root'    => 'grp.org.shops.show.billables.charges.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.billables.charges.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Services"),
                            "tooltip" => __("Services"),
                            "icon"    => ["fal", "fa-concierge-bell"],
                            'root'    => 'grp.org.shops.show.billables.services.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.billables.services.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($user->hasPermissionTo("discounts.$shop->id.view")) {
            $navigation["discounts"] = [
                "root"    => "grp.org.shops.show.discounts.",
                "icon"    => ["fal", "fa-badge-percent"],
                "label"   => __("Offers"),
                "route"   => [
                    "name"       => 'grp.org.shops.show.discounts.dashboard',
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "tooltip" => __("Offers dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            'root'    => 'grp.org.shops.show.discounts.dashboard',
                            "route"   => [
                                "name"       => 'grp.org.shops.show.discounts.dashboard',
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Campaigns"),
                            "tooltip" => __("Campaigns"),
                            "icon"    => ["fal", "fa-comment-dollar"],
                            'root'    => 'grp.org.shops.show.discounts.campaigns.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.discounts.campaigns.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Offers"),
                            "tooltip" => __("Offers"),
                            "icon"    => ["fal", "fa-badge-percent"],
                            'root'    => 'grp.org.shops.show.discounts.offers.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.discounts.offers.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($user->hasPermissionTo("marketing.$shop->id.view")) {
            $navigation["marketing"] = [
                "root"    => "grp.org.shops.show.marketing.",
                "icon"    => ["fal", "fa-bullhorn"],
                "label"   => __("Marketing"),
                "route"   => [
                    "name"       => 'grp.org.shops.show.marketing.dashboard',
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "tooltip" => __("Marketing dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            'root'    => 'grp.org.shops.show.marketing.dashboard',
                            "route"   => [
                                "name"       => 'grp.org.shops.show.marketing.dashboard',
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Newsletters"),
                            "tooltip" => __("Newsletters"),
                            "icon"    => ["fal", "fa-newspaper"],
                            'root'    => 'grp.org.shops.show.marketing.newsletters.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.marketing.newsletters.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Mailshots"),
                            "tooltip" => __("Marketing mailshots"),
                            "icon"    => ["fal", "fa-mail-bulk"],
                            'root'    => 'grp.org.shops.show.marketing.mailshots.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.marketing.mailshots.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Traffic sources"),
                            "tooltip" => __("Traffic sources"),
                            "icon"    => ["fal", "fa-chart-line"],
                            'root'    => 'grp.org.shops.show.marketing.traffic_sources.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.marketing.traffic_sources.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],


                    ],
                ],
            ];
        }

        if ($user->authTo([
            "web.$shop->id.view",
            "group-webmaster.view"
        ])) {
            if ($shop->website) {
                $navigation["web"] = [
                    "root"  => "grp.org.shops.show.web.",
                    "icon"  => ["fal", "fa-globe"],
                    "label" => __("Website"),
                    "route" => [
                        "name"       => "grp.org.shops.show.web.websites.show",
                        "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                    ],

                    "topMenu" => [
                        "subSections" => [


                            [
                                "label"   => __("Website"),
                                "tooltip" => __("Website"),
                                "icon"    => ["fal", "fa-globe"],
                                "root"    => "grp.org.shops.show.web.websites.",

                                "route" => [
                                    "name"       => "grp.org.shops.show.web.websites.show",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],
                            [
                                "label"   => __("Webpages"),
                                "tooltip" => __("Webpages"),
                                "icon"    => ["fal", "fa-browser"],
                                "root"    => "grp.org.shops.show.web.webpages.",

                                "route" => [
                                    "name"       => "grp.org.shops.show.web.webpages.index",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],
                            [
                                "label"   => __("Blogs"),
                                "tooltip" => __("Blogs"),
                                "icon"    => ["fal", "fa-newspaper"],
                                "root"    => "grp.org.shops.show.web.blogs.",

                                "route" => [
                                    "name"       => "grp.org.shops.show.web.blogs.index",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],
                            [
                                "label"   => __("Banners"),
                                "tooltip" => __("Banners"),
                                "icon"    => ["fal", "fa-sign"],
                                'root'    => 'grp.org.shops.show.web.banners.',
                                "route"   => [
                                    "name"       => "grp.org.shops.show.web.banners.index",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],

                            [
                                "label"   => __("Announcements"),
                                "tooltip" => __("Announcements"),
                                "icon"    => ["fal", "fa-megaphone"],
                                'root'    => 'grp.org.shops.show.web.announcements.',
                                "route"   => [
                                    "name"       => "grp.org.shops.show.web.announcements.index",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],
                            [
                                "label"   => __("Analytics"),
                                "tooltip" => __("Analytics"),
                                "icon"    => ["fal", "fa-analytics"],
                                'root'    => 'grp.org.shops.show.web.analytics.',
                                "route"   => [
                                    "name"       => "grp.org.shops.show.web.analytics.dashboard",
                                    "parameters" => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                                ],
                            ],
                        ],
                    ],
                ];
            } else {
                $navigation["web"] = [
                    "scope" => "websites",
                    "icon"  => ["fal", "fa-globe"],
                    "label" => __("Website"),
                    "root"  => "grp.org.shops.show.web.",
                    "route" => [
                        "name"       => "grp.org.shops.show.web.websites.index",
                        "parameters" => [$shop->organisation->slug, $shop->slug],
                    ],

                    "topMenu" => []
                ];
            }
        }

        if ($user->hasPermissionTo("marketing.view")) {
            $navigation["marketing"] = [
                "root"    => "grp.org.shops.show.marketing.",
                "label"   => __("Marketing"),
                "icon"    => ["fal", "fa-bullhorn"],
                "route"   => "grp.marketing.hub",
                "topMenu" => [
                    "subSections" => [],
                ],
            ];
        }

        if ($user->hasAnyPermission(["crm.$shop->id.view", "accounting.$shop->organisation_id.view"])) {
            $navigation["crm"] = [
                "root"  => "grp.org.shops.show.crm.",
                "label" => __("CRM"),
                "icon"  => ["fal", "fa-user"],

                "route" => [
                    "name"       => "grp.org.shops.show.crm.customers.index",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],

                "topMenu" => [
                    "subSections" => [

                        [
                            "label"   => __("Customers"),
                            "tooltip" => __("Customers"),
                            "icon"    => ["fal", "fa-user"],
                            "route"   => [
                                "name"       => "grp.org.shops.show.crm.customers.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Prospects"),
                            "tooltip" => __("Prospects"),
                            "icon"    => ["fal", "fa-user-plus"],
                            "route"   => [
                                "name"       => "grp.org.shops.show.crm.prospects.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($user->hasAnyPermission(["orders.$shop->id.view", "accounting.$shop->organisation_id.view"])) {
            $navigation["ordering"] = [
                "root"    => "grp.org.shops.show.ordering.",
                "scope"   => "shops",
                "label"   => __("Orders"),
                "icon"    => ["fal", "fa-shopping-cart"],
                "route"   => [
                    "name"       => "grp.org.shops.show.ordering.backlog",
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "tooltip" => __("Ordering dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            'root'    => 'grp.org.shops.show.ordering.dashboard',
                            "route"   => [
                                "name"       => 'grp.org.shops.show.ordering.dashboard',
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __('Backlog'),
                            "tooltip" => __('Pending orders'),
                            "icon"    => ["fal", "fa-tasks-alt"],
                            'root'    => 'grp.org.shops.show.ordering.backlog',
                            "route"   => [
                                "name"       => "grp.org.shops.show.ordering.backlog",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Orders"),
                            "tooltip" => __("Orders"),
                            "icon"    => ["fal", "fa-shopping-cart"],
                            'root'    => 'grp.org.shops.show.ordering.orders.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.ordering.orders.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                        [
                            "label"   => __("Delivery notes"),
                            "tooltip" => __("Delivery notes"),
                            "icon"    => ["fal", "fa-truck"],
                            'root'    => 'grp.org.shops.show.ordering.delivery-notes.',
                            "route"   => [
                                "name"       => "grp.org.shops.show.ordering.delivery-notes.index",
                                "parameters" => [$shop->organisation->slug, $shop->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($user->hasPermissionTo("supervisor-products.$shop->id")) {
            $navigation['setting'] = [
                "root"    => "grp.org.shops.show.settings.",
                "icon"    => ["fal", "fa-sliders-h"],
                "label"   => __("Settings"),
                "route"   => [
                    "name"       => 'grp.org.shops.show.settings.edit',
                    "parameters" => [$shop->organisation->slug, $shop->slug],
                ],
                "topMenu" => [
                    "subSections" => [],
                ],
            ];
        }

        return $navigation;
    }
}
