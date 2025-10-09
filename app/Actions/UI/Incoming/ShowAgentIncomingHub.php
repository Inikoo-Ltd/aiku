<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 21:38:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Incoming;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\Inventory\Warehouse;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentIncomingHub extends OrgAction
{
    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function authorize(ActionRequest $request): bool
    {

        return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
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

        return Inertia::render(
            'Org/Incoming/IncomingHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => 'incoming',
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-arrow-to-bottom'],
                        'title' => __('Incoming')
                    ],
                    'title' => __('Incoming Hub'),
                ],
                'box_stats'   => [
                    [
                        'name'  => __('Supplier Deliveries'),
                        // todo add agent_supplier_deliveries  to agent_stats and show it here
                        'value' => $warehouse->organisation->procurementStats->number_stock_deliveries,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.stock_deliveries.index',
                            'parameters' => $request->route()->originalParameters()
                        ],
                        'icon'  => [
                            'icon'    => 'fal fa-truck-container',
                            'tooltip' => __('Supplier Deliveries')
                        ]
                    ],

                ],


            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.agent_incoming.backlog',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Goods in'),
                    ]
                ]
            ]
        );
    }

}
