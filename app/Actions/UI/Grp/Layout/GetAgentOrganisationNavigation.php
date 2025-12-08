<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-10h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\UI\Grp\Layout;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentOrganisationNavigation
{
    use AsAction;
    use WithLayoutNavigation;

    public function handle(User $user, Organisation $organisation): array
    {
        $navigation = [];

        $navigation = $this->getWarehouseNavs($user, $organisation, $navigation);

        if ($user->hasPermissionTo("procurement.$organisation->id.view")) {
            $navigation['procurement'] = [
                'root' => 'grp.org.procurement',
                'label' => __('procurement'),
                'icon' => ['fal', 'fa-box-usd'],
                'route' => [
                    'name' => 'grp.org.procurement.dashboard',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'icon' => ['fal', 'fa-chart-network'],
                            'root' => 'grp.org.procurement.dashboard',
                            'route' => [
                                'name' => 'grp.org.procurement.dashboard',
                                'parameters' => [$organisation->slug],
                            ],
                        ],

                        [
                            'label' => __('suppliers'),
                            'icon' => ['fal', 'fa-person-dolly'],
                            'root' => 'grp.org.procurement.org_suppliers.',
                            'route' => [
                                'name' => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => [$organisation->slug],
                            ],
                        ],
                        [
                            'label' => __('partners'),
                            'icon' => ['fal', 'fa-users-class'],
                            'root' => 'grp.org.procurement.org_partners.',
                            'route' => [
                                'name' => 'grp.org.procurement.org_partners.index',
                                'parameters' => [$organisation->slug],
                            ],
                        ],
                        [
                            'label' => __('purchase orders'),
                            'icon' => ['fal', 'fa-clipboard-list'],
                            'root' => 'grp.org.procurement.purchase_orders.',
                            'route' => [
                                'name' => 'grp.org.procurement.purchase_orders.index',
                                'parameters' => [$organisation->slug],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $navigation = $this->getAccountingNavs($user, $organisation, $navigation);

        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);

        $navigation = $this->getReportsNavs($user, $organisation, $navigation);

        return $this->getSettingsNavs($user, $organisation, $navigation);

    }
}
