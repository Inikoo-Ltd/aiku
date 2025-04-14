<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 18:39:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Stubs\Migrations\HasInventoryStats;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentInventoryDashboard extends OrgAction
{
    use HasInventoryStats;
    use WithInventoryAuthorisation;


    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        /** @var Agent $agent */
        $agent = $this->organisation->agent;

        $routeParameters = $request->route()->originalParameters();


        return Inertia::render(
            'Org/Inventory/InventoryDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Inventory'),
                'pageHead'     => [
                    'title'     => __('Inventory'),
                    'icon'      => [
                        'icon' => 'fal fa-pallet-alt'
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('inventory')
                    ],
                ],
                'flatTreeMaps' => [
                    [

                        [
                            'name'        => 'SKUs',
                            'icon'        => ['fal', 'fa-box'],
                            'description' => __('current'),
                            'route'       => [
                                'name'       => 'grp.org.warehouses.show.agent_inventory.supplier_products.index',
                                'parameters' => $routeParameters
                            ],
                            'index'       => [
                                'number' => $this->organisation->agent->stats->number_current_supplier_products
                            ],
                            'sub_data'    => $this->getDashboardStats($agent)['supplier_products']['cases']
                        ]
                    ]
                ],
                'dashboard'    => []
            ]
        );
    }


    public function getDashboardStats(Agent $agent): array
    {
        $stats = [];

        $stats['supplier_products'] = [
            'label' => __('SKUs'),
            'count' => $this->organisation->inventoryStats->number_current_org_stocks
        ];

        foreach (SupplierProductStateEnum::cases() as $case) {
            $count = SupplierProductStateEnum::count($agent)[$case->value];

            $stats['supplier_products']['cases'][] = [
                'value' => $case->value,
                'icon'  => SupplierProductStateEnum::stateIcon()[$case->value],
                'count' => $count,
                'label' => SupplierProductStateEnum::labels()[$case->value]
            ];
        }

        return $stats;
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Agent Inventory'),
                        ]
                    ]
                ]
            );
    }
}
