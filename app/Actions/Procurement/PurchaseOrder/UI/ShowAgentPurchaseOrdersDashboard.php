<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\Agent\UI\GetAgentDashboardPurchaseOrders;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentPurchaseOrdersDashboard extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return GetAgentDashboardPurchaseOrders::run($agent, $organisation);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentPurchaseOrdersDashboard',
            [
                'title'       => __('Purchase orders'),
                'breadcrumbs' => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Purchase orders'),
                                'route' => [
                                    'name'       => 'grp.org.agent.purchase_orders.dashboard',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => __('Purchase orders'),
                        'icon'  => 'fal fa-clipboard-list',
                    ],
                    'title' => __('Purchase orders'),
                ],
                'data'        => $data,
            ]
        );
    }
}
