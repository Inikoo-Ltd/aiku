<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-10h-27m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\UI\Grp\Layout;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDigitalAgencyOrganisationNavigation
{
    use AsAction;
    use WithLayoutNavigation;

    public function handle(User $user, Organisation $organisation): array
    {
        $navigation = [];

        if ($user->hasAnyPermission(
            [
                'org-supervisor.'.$organisation->id,
                'shops-view.'.$organisation->id
            ]
        )) {
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
                            'label'   => __('dashboard'),
                            'tooltip' => __('Dashboard'),
                        ]
                    ]
                ]

            ];
        }


        $navigation = $this->getShopsCrmNavs($user, $organisation, $navigation);
        $navigation = $this->getAccountingNavs($user, $organisation, $navigation);
        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);
        $navigation = $this->getReportsNavs($user, $organisation, $navigation);

        return $this->getSettingsNavs($user, $organisation, $navigation);
    }

    private function getShopsCrmNavs(User $user, Organisation $organisation, array $navigation): array
    {
        $shops = $user->authorisedShops
            ->where('organisation_id', $organisation->id)
            ->filter(fn (Shop $shop) => $shop->state !== ShopStateEnum::CLOSED && $user->authTo("crm.$shop->id.view"));

        foreach ($shops as $shop) {
            $suffix = $shops->count() > 1 ? ' · '.$shop->name : '';

            $navigation['shop_'.$shop->slug] = [
                'label'   => __('Dashboard').$suffix,
                'icon'    => ['fal', 'fa-store-alt'],
                'root'    => 'grp.org.shops.show.dashboard.show',
                'route'   => [
                    'name'       => 'grp.org.shops.show.dashboard.show',
                    'parameters' => [$organisation->slug, $shop->slug],
                ],
                'topMenu' => [
                    'subSections' => []
                ],
            ];

            $navigation['customers_'.$shop->slug] = [
                'label'   => __('Customers').$suffix,
                'icon'    => ['fal', 'fa-user'],
                'root'    => 'grp.org.shops.show.crm.customers.',
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [$organisation->slug, $shop->slug],
                ],
                'topMenu' => [
                    'subSections' => []
                ],
            ];

            if ($user->authTo("crm.$shop->id.prospects.view")) {
                $navigation['prospects_'.$shop->slug] = [
                    'label'   => __('Prospects').$suffix,
                    'icon'    => ['fal', 'fa-user-plus'],
                    'root'    => 'grp.org.shops.show.crm.prospects.',
                    'route'   => [
                        'name'       => 'grp.org.shops.show.crm.prospects.index',
                        'parameters' => [$organisation->slug, $shop->slug],
                    ],
                    'topMenu' => [
                        'subSections' => []
                    ],
                ];
            }

            $navigation['appointments_'.$shop->slug] = [
                'label'   => __('Appointments').$suffix,
                'icon'    => ['fal', 'fa-calendar'],
                'root'    => 'grp.org.shops.show.crm.appointments.',
                'route'   => [
                    'name'       => 'grp.org.shops.show.crm.appointments.index',
                    'parameters' => [$organisation->slug, $shop->slug],
                ],
                'topMenu' => [
                    'subSections' => []
                ],
            ];

            $navigation['outboxes_'.$shop->slug] = [
                'label'   => __('Outboxes').$suffix,
                'icon'    => ['fal', 'fa-inbox-out'],
                'root'    => 'grp.org.shops.show.dashboard.comms.',
                'route'   => [
                    'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.index',
                    'parameters' => [$organisation->slug, $shop->slug],
                ],
                'topMenu' => [
                    'subSections' => []
                ],
            ];

            if ($user->authTo("supervisor-products.$shop->id")) {
                $navigation['settings_'.$shop->slug] = [
                    'label'   => __('Shop settings').$suffix,
                    'icon'    => ['fal', 'fa-sliders-h'],
                    'root'    => 'grp.org.shops.show.settings.',
                    'route'   => [
                        'name'       => 'grp.org.shops.show.settings.edit',
                        'parameters' => [$organisation->slug, $shop->slug],
                    ],
                    'topMenu' => [
                        'subSections' => []
                    ],
                ];
            }
        }

        return $navigation;
    }
}
