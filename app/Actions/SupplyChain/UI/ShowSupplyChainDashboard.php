<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:12:27 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainDashboard extends GrpAction
{
    use AsAction;
    use WithInertia;


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("supply-chain.{$this->group->id}.view");
    }


    public function asController(ActionRequest $request): void
    {
        $this->initialisation(app('group'), $request);
    }


    public function htmlResponse(): Response
    {

        return Inertia::render(
            'SupplyChain/SupplyChainDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('supply chain'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-box-usd'],
                        'title' => __('Supply Chain')
                    ],
                    'title' => __('Supply Chain'),
                ],
                'flatTreeMaps' => [

                    [

                        [
                            'name'  => __('agents'),
                            'icon'  => ['fal', 'fa-people-arrows'],
                            'route'  => [
                                'name' => 'grp.supply-chain.agents.index'
                            ],
                            'index' => [
                                'number' => $this->group->supplyChainStats->number_active_agents
                            ],
                        ],
                        [
                            'name'  => __('suppliers'),
                            'icon'  => ['fal', 'fa-person-dolly'],
                            'route'  => ['name' => 'grp.supply-chain.suppliers.index'],
                            'index' => [
                                'number' => $this->group->supplyChainStats->number_active_independent_suppliers
                            ],

                        ],
                        [
                            'name'      => __('Supplier Products'),
                            'shortName' => __('products'),
                            'icon'      => ['fal', 'fa-box-usd'],
                            'route'      => ['name' => 'grp.supply-chain.supplier_products.index'],
                            'index'     => [
                                'number' => $this->group->supplyChainStats->number_current_supplier_products
                            ],

                        ],
                    ],

                ],
                'dashboard_stats'   => [
                    'widgets'   => [
                        'column_count'  => 1,
                        'components'    => [
                            [
                                'type'      => 'flat_tree_map',  // 'basic'
                                // 'col_span'  => '2',
                                // 'row_span'  => '2',
                                'visual'    => [],
                                'data'      => [
                                    'nodes'     => [
                                        [
                                            'name'  => __('Agents'),
                                            'icon'  => ['fal', 'fa-people-arrows'],
                                            'route'  => [
                                                'name' => 'grp.supply-chain.agents.index'
                                            ],
                                            'index' => [
                                                'number' => $this->group->supplyChainStats->number_active_agents
                                            ],
                                        ],
                                        [
                                            'name'  => __('Suppliers'),
                                            'icon'  => ['fal', 'fa-person-dolly'],
                                            'route'  => ['name' => 'grp.supply-chain.suppliers.index'],
                                            'index' => [
                                                'number' => $this->group->supplyChainStats->number_active_independent_suppliers
                                            ],

                                        ],
                                        [
                                            'name'      => __('Supplier Products'),
                                            'shortName' => __('products'),
                                            'icon'      => ['fal', 'fa-box-usd'],
                                            'route'      => ['name' => 'grp.supply-chain.supplier_products.index'],
                                            'index'     => [
                                                'number' => $this->group->supplyChainStats->number_current_supplier_products
                                            ],

                                        ],
                                    ],
                                    // 'mode'  => 'compact'
                                ],
                            ]
                        ],
                    ]
                ]


            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.supply-chain.dashboard'
                            ],
                            'label' => __('Supply chain'),
                        ]
                    ]
                ]
            );
    }


}
