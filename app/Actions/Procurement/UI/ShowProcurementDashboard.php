<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:08:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Search\GetSearchDemandOpportunities;
use App\Actions\UI\WithInertia;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\Shipper;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProcurementDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use AsAction;
    use WithInertia;
    use WithAgentOrganisation;



    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }


    private function getDashboardNumbers(): array
    {
        $agent = $this->getOrganisationAgent($this->organisation);

        if (!$agent) {
            $procurementStats = $this->organisation->procurementStats;

            return [
                'suppliers'         => $procurementStats->number_active_independent_org_suppliers,
                'supplier_products' => $procurementStats->number_current_org_supplier_products,
                'purchase_orders'   => $procurementStats->number_purchase_orders,
                'stock_deliveries'  => $procurementStats->number_stock_deliveries,
            ];
        }

        return [
            'suppliers'         => OrgSupplier::where('agent_id', $agent->id)->count(),
            'supplier_products' => OrgSupplierProduct::whereIn(
                'org_agent_id',
                OrgAgent::where('agent_id', $agent->id)->select('id')
            )->count(),
            'purchase_orders'   => PurchaseOrder::where('agent_id', $agent->id)->count(),
            'stock_deliveries'  => StockDelivery::where('agent_id', $agent->id)->count(),
        ];
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $numbers = $this->getDashboardNumbers();
        $agents  = null;

        if ($this->organisation->type === OrganisationTypeEnum::SHOP) {
            $agents = [
                'name'         => __('Agents'),
                'icon'         => ['fal', 'fa-people-arrows'],
                'route'         => [
                    'name'       => 'grp.org.procurement.org_agents.index',
                    'parameters' => ['organisation' => $this->organisation->slug]
                ],
                'index'        => [
                    'number' => $this->organisation->procurementStats->number_active_org_agents
                ],
            ];
        }

        return Inertia::render(
            'Procurement/ProcurementDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Procurement'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-box-usd'],
                        'title' => __('Procurement')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Procurement')
                    ],
                    'title' => __('Procurement'),
                ],

                'shippers' => Shipper::query()->get(),
                'search_demand' => GetSearchDemandOpportunities::run($this->group, $this->organisation),
                'flatTreeMaps' => [

                    array_filter([
                        $agents,
                        [
                            'name'         => __('Suppliers'),
                            'icon'         => ['fal', 'fa-person-dolly'],
                            'route'         => [
                                'name'       => 'grp.org.procurement.org_suppliers.index',
                                'parameters' => ['organisation' => $this->organisation->slug]
                            ],
                            'index'        => [
                                'number' => $numbers['suppliers']
                            ],
                        ],
                        [
                            'name'         => __('Supplier Products'),
                            'shortName'    => __('Products'),
                            'icon'         => ['fal', 'fa-box-usd'],
                            'route'         => [
                                'name'       => 'grp.org.procurement.org_supplier_products.index',
                                'parameters' => ['organisation' => $this->organisation->slug]
                            ],
                            'index'        => [
                                'number' => $numbers['supplier_products']
                            ]
                        ],
                    ]),

                    [
                        [
                            'name'  => __('Purchase Orders'),
                            'icon'  => ['fal', 'fa-clipboard-list'],
                            'route'  => ['name' => 'grp.org.procurement.purchase_orders.index', 'parameters' => ['organisation' => $this->organisation->slug]],
                            'index' => [
                                'number' => $numbers['purchase_orders']
                            ]

                        ],
                        [
                            'name'  => __('Stock Deliveries'),
                            'icon'  => ['fal', 'fa-truck-container'],
                            'route'  => ['name' => 'grp.org.procurement.stock_deliveries.index', 'parameters' => ['organisation' => $this->organisation->slug]],
                            'index' => [
                                'number' => $numbers['stock_deliveries']
                            ]

                        ],
                    ],
                ]

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.dashboard',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Procurement'),
                        ]
                    ]
                ]
            );
    }


}
