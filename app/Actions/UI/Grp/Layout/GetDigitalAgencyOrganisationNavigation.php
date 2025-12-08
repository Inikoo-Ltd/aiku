<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-10h-27m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\UI\Grp\Layout;

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
                'shops-view.'.$organisation->id,
            ]
        )) {
            $navigation['shops_index'] = [
                'label' => __('Shops'),
                'scope' => 'shops',
                'icon' => ['fal', 'fa-store-alt'],
                'root' => 'grp.org.shops.index',
                'route' => [
                    'name' => 'grp.org.shops.index',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'label' => __('dashboard'),
                            'tooltip' => __('Dashboard'),
                        ],
                    ],
                ],

            ];
        }

        // todo shops nav

        $navigation = $this->getAccountingNavs($user, $organisation, $navigation);
        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);
        $navigation = $this->getReportsNavs($user, $organisation, $navigation);

        return $this->getSettingsNavs($user, $organisation, $navigation);
    }
}
