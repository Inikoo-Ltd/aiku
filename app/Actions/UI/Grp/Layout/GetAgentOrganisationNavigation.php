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

        if ($user->authTo("procurement.$organisation->id.view")) {
            $subSections = [
                $this->procurementSubSection(__('Dashboard'), 'fa-chart-network', 'grp.org.procurement.dashboard', 'grp.org.procurement.dashboard', $organisation),
                $this->procurementSubSection(__('Purchase Orders'), 'fa-clipboard-list', 'grp.org.procurement.purchase_orders.', 'grp.org.procurement.purchase_orders.index', $organisation),
                $this->procurementSubSection(__('Supplier Purchase Orders'), 'fa-clipboard-list', 'grp.org.procurement.agent_supplier_purchase_orders.', 'grp.org.procurement.agent_supplier_purchase_orders.index', $organisation),
                $this->procurementSubSection(__('Stock Deliveries'), 'fa-truck-container', 'grp.org.procurement.stock_deliveries.', 'grp.org.procurement.stock_deliveries.index', $organisation),
                $this->procurementSubSection(__('Suppliers'), 'fa-person-dolly', 'grp.org.procurement.org_suppliers.', 'grp.org.procurement.org_suppliers.index', $organisation),
                $this->procurementSubSection(__('Inbox'), 'fa-inbox', 'grp.org.procurement.supplier_messages.', 'grp.org.procurement.supplier_messages.index', $organisation),
            ];

            if ($user->authTo(['org-admin.'.$organisation->id, 'org-supervisor.'.$organisation->id.'.procurement'])) {
                $subSections[] = $this->procurementSubSection(__('Settings'), 'fa-cog', 'grp.org.procurement.settings.', 'grp.org.procurement.settings.edit', $organisation);
            }

            $navigation['procurement'] = [
                'root'    => 'grp.org.procurement',
                'label'   => __('Procurement'),
                'icon'    => ['fal', 'fa-box-usd'],
                'route'   => [
                    'name'       => 'grp.org.procurement.dashboard',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => $subSections
                ]
            ];
        }

        return $this->getHumanResourcesNavs($user, $organisation, $navigation);
    }

    private function procurementSubSection(string $label, string $icon, string $root, string $routeName, Organisation $organisation): array
    {
        return [
            'label' => $label,
            'icon'  => ['fal', $icon],
            'root'  => $root,
            'route' => [
                'name'       => $routeName,
                'parameters' => [$organisation->slug],
            ]
        ];
    }
}
