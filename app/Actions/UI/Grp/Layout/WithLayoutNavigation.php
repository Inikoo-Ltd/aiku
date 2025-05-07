<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 14:46:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

trait WithLayoutNavigation
{
    public function getWarehouseNavs(User $user, Organisation $organisation, array $navigation): array
    {
        if ($user->authTo(['org-supervisor.'.$organisation->id, 'warehouses-view.'.$organisation->id])) {

            $navigation['warehouses_index'] = [
                'label'   => __('Warehouses'),
                'scope'   => 'warehouses',
                'icon'    => ['fal', 'fa-warehouse-alt'],
                'root'    => 'grp.org.warehouses.index',
                'route'   => [
                    'name'       => 'grp.org.warehouses.index',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'links' => [
                        [
                            'label'   => __('dashboard'),
                            'tooltip' => __('Dashboard'),
                        ]
                    ]
                ]
            ];
        }

        $navigation['warehouses_navigation'] = [];

        /** @var Warehouse $warehouse */
        foreach ($user->authorisedWarehouses()->where('organisation_id', $organisation->id)->get() as $warehouse) {

            if ($warehouse->organisation->type == OrganisationTypeEnum::AGENT) {
                $navigation['warehouses_navigation'][$warehouse->slug] = GetAgentWarehouseNavigation::run($warehouse, $user);
            } else {
                $navigation['warehouses_navigation'][$warehouse->slug] = GetWarehouseNavigation::run($warehouse, $user);
            }

        }

        return $navigation;
    }

    public function getAccountingNavs(User $user, Organisation $organisation, array $navigation): array
    {

        if ($user->authTo("accounting.$organisation->id.view")) {
            $navigation['accounting'] = [
                'root'  => 'grp.org.accounting',
                'scope' => 'shops',
                'label' => __('Accounting'),
                'icon'  => ['fal', 'fa-abacus'],
                'route' => [
                    'name'       => 'grp.org.accounting.dashboard',
                    'parameters' => [$organisation->slug],
                ],


                'topMenu' => [
                    'subSections' => [
                        [
                            'icon'  => ['fal', 'fa-chart-network'],
                            'root'  => 'grp.org.accounting.dashboard',
                            'route' => [
                                'name'       => 'grp.org.accounting.dashboard',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        /*
                        [
                            'label' => __('Providers'),
                            'icon'  => ['fal', 'fa-cash-register'],
                            'root'  => 'grp.org.accounting.org_payment_service_providers.',
                            'route' => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        */
                        [
                            'label' => __('Accounts'),
                            'icon'  => ['fal', 'fa-money-check-alt'],
                            'root'  => 'grp.org.accounting.payment-accounts.',
                            'route' => [
                                'name'       => 'grp.org.accounting.payment-accounts.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        [
                            'label' => __('Payments'),
                            'icon'  => ['fal', 'fa-coins'],
                            'root'  => 'grp.org.accounting.payments.',
                            'route' => [
                                'name'       => 'grp.org.accounting.payments.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        [
                            'label' => __('Invoices'),
                            'icon'  => ['fal', 'fa-file-invoice-dollar'],
                            'root'  => 'grp.org.accounting.invoices.',
                            'route' => [
                                'name'       => 'grp.org.accounting.invoices.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        [
                            'label' => __('Customers Balances'),
                            'icon'  => ['fal', 'fa-piggy-bank'],
                            'root'  => 'grp.org.accounting.balances.',
                            'route' => [
                                'name'       => 'grp.org.accounting.balances.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],

                    ],
                ]
            ];
        }
        return  $navigation;
    }

    public function getHumanResourcesNavs(User $user, Organisation $organisation, array $navigation): array
    {
        if ($user->authTo("human-resources.$organisation->id.view")) {
            $navigation['hr'] = [
                'label'   => __('human resources'),
                'icon'    => ['fal', 'fa-user-hard-hat'],
                'root'    => 'grp.org.hr',
                'route'   => [
                    'name'       => 'grp.org.hr.dashboard',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [

                        [
                            "tooltip" => __("Dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            "root"    => "grp.org.hr.dashboard",
                            "route"   => [
                                "name"       => "grp.org.hr.dashboard",
                                'parameters' => [$organisation->slug],
                            ],
                        ],

                        [
                            'tooltip' => __('working place'),
                            'icon'    => ['fal', 'fa-building'],
                            'root'    => 'grp.org.hr.workplaces.',
                            'route'   => [
                                'name'       => 'grp.org.hr.workplaces.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],


                        [
                            'tooltip' => __('Responsibilities'),
                            'icon'    => ['fal', 'fa-clipboard-list-check'],
                            'root'    => 'grp.org.hr.job_positions.',
                            'route'   => [
                                'name'       => 'grp.org.hr.job_positions.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],


                        [
                            'label' => __('employees'),
                            'icon'  => ['fal', 'fa-user-hard-hat'],
                            'root'  => 'grp.org.hr.employees.',
                            'route' => [
                                'name'       => 'grp.org.hr.employees.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        // [
                        //     'label' => __('calendar'),
                        //     'icon'  => ['fal', 'fa-calendar'],
                        //     'root'  => 'grp.org.hr.calendars.',
                        //     'route' => [
                        //         'name'       => 'grp.org.hr.calendars.index',
                        //         'parameters' => [$organisation->slug],

                        //     ]
                        // ],

                        [
                            'label' => __('clocking machines'),
                            'icon'  => ['fal', 'fa-chess-clock'],
                            'root'  => 'grp.org.hr.clocking_machines.',
                            'route' => [
                                'name'       => 'grp.org.hr.clocking_machines.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        [
                            'label' => __('timesheets'),
                            'icon'  => ['fal', 'fa-stopwatch'],
                            'root'  => 'grp.org.hr.timesheets.',
                            'route' => [
                                'name'       => 'grp.org.hr.timesheets.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],




                    ]
                ]
            ];
        }
        return $navigation;
    }

    public function getReportsNavs(User $user, Organisation $organisation, array $navigation): array
    {
        if ($user->authTo('org-reports.'.$organisation->id)) {
            $navigation['reports'] = [
                'label'   => __('Reports'),
                'tooltip' => __('Reports'),
                'icon'    => ['fal', 'fa-chart-line'],
                'root'    => 'grp.org.reports',

                'route' => [
                    'name'       => 'grp.org.reports.index',
                    'parameters' => [$organisation->slug],
                ],

                'topMenu' => [

                    'subSections' => [

                    ]
                ],
            ];
        }
        return $navigation;
    }

    public function getSettingsNavs(User $user, Organisation $organisation, array $navigation): array
    {
        if ($user->authTo('org-supervisor.'.$organisation->id)) {
            $navigation['setting'] = [
                "root"    => "grp.org.settings.",
                "icon"    => ["fal", "fa-sliders-h"],
                "label"   => __("Settings"),
                "route"   => [
                    "name"       => 'grp.org.settings.edit',
                    "parameters" => [$organisation->slug],
                ],
                "topMenu" => [
                    "subSections" => [

                    ],
                ],
            ];
        }

        return $navigation;
    }

    public function getLocationsNavs(User $user, Warehouse $warehouse, array $navigation)
    {
        if ($user->hasPermissionTo("locations.$warehouse->id.view")) {
            $navigation["warehouse"] = [
                "root"    => "grp.org.warehouses.show.infrastructure.",
                "label"   => __("locations"),
                "icon"    => ["fal", "fa-inventory"],
                "route"   => [
                    "name"       => "grp.org.warehouses.show.infrastructure.dashboard",
                    "parameters" => [$warehouse->organisation->slug, $warehouse->slug],
                ],
                "topMenu" => [
                    "subSections" => [
                        [
                            "root"    => "grp.org.warehouses.show.infrastructure.dashboard",
                            "tooltip" => __("warehouses"),
                            "icon"    => ["fal", "fa-warehouse-alt"],
                            "route"   => [
                                "name"       => "grp.org.warehouses.show.infrastructure.dashboard",
                                "parameters" => [$warehouse->organisation->slug, $warehouse->slug],
                            ],
                            "label"   => null,
                        ],
                        [
                            "root"    => "grp.org.warehouses.show.infrastructure.warehouse_areas.",
                            "label"   => __("areas"),
                            "tooltip" => __("Warehouse Areas"),
                            "icon"    => ["fal", "fa-map-signs"],
                            "route"   => [
                                "name"       =>
                                    "grp.org.warehouses.show.infrastructure.warehouse_areas.index",
                                "parameters" => [$warehouse->organisation->slug, $warehouse->slug],
                            ],
                        ],
                        [
                            "root"    => "grp.org.warehouses.show.infrastructure.locations.",
                            "label"   => __("locations"),
                            "tooltip" => __("Locations"),
                            "icon"    => ["fal", "fa-inventory"],
                            "route"   => [
                                "name"       => "grp.org.warehouses.show.infrastructure.locations.index",
                                "parameters" => [$warehouse->organisation->slug, $warehouse->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $navigation;
    }

}
