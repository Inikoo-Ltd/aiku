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
                            'label' => __('Supplier Products'),
                            'icon'  => ['fal', 'fa-box-usd'],
                            'root'  => 'grp.org.procurement.org_supplier_products.',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_supplier_products.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Purchase Orders'),
                            'icon'  => ['fal', 'fa-clipboard-list'],
                            'root'  => 'grp.org.procurement.purchase_orders.',
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Supplier Purchase Orders'),
                            'icon'  => ['fal', 'fa-clipboard-list'],
                            'root'  => 'grp.org.procurement.agent_supplier_purchase_orders.',
                            'route' => [
                                'name'       => 'grp.org.procurement.agent_supplier_purchase_orders.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                        [
                            'label' => __('Stock Deliveries'),
                            'icon'  => ['fal', 'fa-truck-container'],
                            'root'  => 'grp.org.procurement.stock_deliveries.',
                            'route' => [
                                'name'       => 'grp.org.procurement.stock_deliveries.index',
                                'parameters' => [$organisation->slug],
                            ]
                        ],
                    ]
                ]
            ];
        }

        // $navigation = $this->getAccountingNavs($user, $organisation, $navigation); // Still no need

        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);


        // $navigation = $this->getReportsNavs($user, $organisation, $navigation); // Still no need

        return $this->getSettingsNavs($user, $organisation, $navigation);

    }
}
