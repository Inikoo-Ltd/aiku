<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 21:46:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentDispatchHub extends OrgAction
{
    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("dispatching.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, []);

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        /** @var Agent $agent */
        $agent = $warehouse->organisation->agent;
        $stats = $this->agentOrganisationStats($agent, $request);

        return Inertia::render(
            'Org/Dispatching/DispatchHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title' => 'dispatch',
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-conveyor-belt-alt'],
                        'title' => __('Locations'),
                    ],
                    'title' => __('Dispatching backlog'),
                ],
                'box_stats' => $stats,

            ]
        );
    }

    public function agentOrganisationStats(Agent $agent, ActionRequest $request): array
    {
        return [
            [
                'name' => __('Deliveries'),
                // toto do this properly
                'value' => $agent->stats->number_current_stock_deliveries,
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.delivery-notes',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'icon' => [
                    'icon' => 'fal fa-truck',
                    'tooltip' => __('Deliveries'),
                ],
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.backlog',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Dispatching'),
                    ],
                ],
            ]
        );
    }
}
