<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:12:27 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetSearchDemandOpportunities;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainDashboard extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use AsAction;
    use WithInertia;



    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(app('group'), $request);
    }


    public function htmlResponse(): Response
    {

        return Inertia::render(
            'SupplyChain/SupplyChainDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('Supply chain'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-box-usd'],
                        'title' => __('Supply chain')
                    ],
                    'title' => __('Supply chain'),
                ],
                'search_demand' => GetSearchDemandOpportunities::run($this->group),
                'flatTreeMaps' => [

                    [

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

                ],
                'dashboard_stats'   => [
                    'widgets'   => [
                        'column_count'  => 1,
                        'components'    => [
                            [
                                'type'      => 'flat_tree_map',  // 'basic'
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
                                            'shortName' => __('Products'),
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
