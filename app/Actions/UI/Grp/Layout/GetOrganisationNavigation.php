<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:12:46 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetOrganisationNavigation
{
    use AsAction;
    use WithLayoutNavigation;

    public function handle(User $user, Organisation $organisation): array
    {
        $navigation = [];


        if ($user->authTo(['accounting.' . $organisation->id . '.view', 'org-supervisor.' . $organisation->id, 'shops-view.' . $organisation->id])) {
            $navigation['shops_index'] = [
                'label'   => __('Shops'),
                'scope'   => 'shops',
                'icon'    => ['fal', 'fa-store-alt'],
                'root'    => 'grp.org.shops.index',
                'route'   => [
                    'name'       => 'grp.org.shops.index',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label'   => __('Dashboard'),
                            'tooltip' => __('Dashboard'),
                        ]
                    ]
                ]

            ];
        }

        $shops_navigation = [];
        foreach ($user->authorisedShops->where('organisation_id', $organisation->id) as $shop) {
            $shops_navigation[$shop->slug] = [
                'type'          => $shop->type,
                'state'         => $shop->state,
                'subNavigation' => GetShopNavigation::run($shop, $user)
            ];
        }


        if ($user->authTo(['org-supervisor.' . $organisation->id, 'fulfilments-view.' . $organisation->id])) {
            $navigation['fulfilments_index'] = [
                'label'   => __('Fulfilment shops'),
                'root'    => 'grp.org.fulfilments.index',
                'icon'    => ['fal', 'fa-store-alt'],
                'route'   => [
                    'name'       => 'grp.org.fulfilments.index',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label'   => __('Dashboard'),
                            'tooltip' => __('Dashboard'),
                        ]
                    ]
                ]
            ];
        }

        $fulfilments_navigation = [];
        foreach ($user->authorisedFulfilments->where('organisation_id', $organisation->id) as $fulfilment) {
            $fulfilments_navigation[$fulfilment->slug] = [
                'type'          => $fulfilment->type ?? 'fulfilment',
                'subNavigation' => GetFulfilmentNavigation::run($fulfilment, $user)
            ];
        }

        $navigation['shops_fulfilments_navigation'] = [
            'shops_navigation'       => [
                'label'      => __('Shop'),
                'icon'       => "fal fa-store-alt",
                'navigation' => $shops_navigation
            ],
            'fulfilments_navigation' => [
                'label'      => __('Fulfilment'),
                'icon'       => "fal fa-hand-holding-box",
                'navigation' => $fulfilments_navigation
            ]
        ];

        $navigation['productions_navigation'] = [];
        foreach ($user->authorisedProductions->where('organisation_id', $organisation->id) as $production) {
            $navigation['productions_navigation'][$production->slug] = GetProductionNavigation::run($production, $user);
        }


        $navigation = $this->getWarehouseNavs($user, $organisation, $navigation);


        if ($user->authTo("procurement.$organisation->id.view")) {
            $navigation['procurement'] = [
                'root'    => 'grp.org.procurement',
                'label'   => __('Procurement'),
                'icon'    => ['fal', 'fa-box-usd'],
                'route'   => [
                    'name'       => 'grp.org.procurement.dashboard',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'icon'  => ['fal', 'fa-chart-network'],
                            'root'  => 'grp.org.procurement.dashboard',
                            'route' => [
                                'name'       => 'grp.org.procurement.dashboard',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Agents'),
                            'icon'  => ['fal', 'fa-people-arrows'],
                            'root'  => 'grp.org.procurement.org_agents.',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.index',
                                'parameters' => [$organisation->slug],

                            ]
                        ],
                        [
                            'label' => __('Suppliers'),
                            'icon'  => ['fal', 'fa-person-dolly'],
                            'root'  => 'grp.org.procurement.org_suppliers.',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Partners'),
                            'icon'  => ['fal', 'fa-users-class'],
                            'root'  => 'grp.org.procurement.org_partners.',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Purchase orders'),
                            'icon'  => ['fal', 'fa-clipboard-list'],
                            'root'  => 'grp.org.procurement.purchase_orders.',
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                    ]
                ]
            ];
        }


        $navigation = $this->getAccountingNavs($user, $organisation, $navigation);


        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);


        $navigation['overview'] = [
            'label'   => __('Overview'),
            'tooltip' => __('Overview'),
            'icon'    => ['fal', 'fa-mountains'],
            'root'    => 'grp.org.overview.',

            'route' => [
                'name'       => 'grp.org.overview.hub',
                'parameters' => [$organisation->slug],
            ],

            'topMenu' => []
        ];

        $navigation = $this->getReportsNavs($user, $organisation, $navigation);

        // $navigation['tags'] = [
        //     'label'   => __('Tags'),
        //     'tooltip' => __('Tags'),
        //     'icon'    => ['fal', 'fa-tags'],
        //     'root'    => 'grp.org.tags.',
        //     'route'   => [
        //         'name'       => 'grp.org.tags.show',
        //         'parameters' => [$organisation->slug],
        //     ],
        //     'topMenu' => [],
        // ];

        $navigation['crm_agents'] = [
            'label'   => __('CRM Agents'),
            'tooltip' => __('CRM Agents'),
            'icon'    => ['fal', 'fa-headset'],
            'root'    => 'grp.org.crm.agents.show',
            'route'   => [
                'name'       => 'grp.org.crm.agents.show',
                'parameters' => [$organisation->slug],
            ],
            'topMenu' => [],
        ];

        return $this->getSettingsNavs($user, $organisation, $navigation);
    }
}
