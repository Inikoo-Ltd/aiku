<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 16:54:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Actions\Traits\Dashboards\WithLatestStockHistory;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Stubs\Migrations\HasInventoryStats;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowInventoryDashboard extends OrgAction
{
    use HasInventoryStats;
    use WithInventoryAuthorisation;
    use WithLatestStockHistory;


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();


        return Inertia::render(
            'Org/Inventory/InventoryDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Inventory'),
                'pageHead'     => [
                    'title'     => __('Inventory'),
                    'model'     => __('Warehouse'),
                    'icon'      => [
                        'icon' => 'fal fa-pallet-alt'
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Inventory')
                    ],
                ],
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('SKOs Families'),
                            'icon'  => ['fal', 'fa-boxes-alt'],
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stock_families.index',
                                'parameters' => $routeParameters
                            ],
                            'index' => [
                                'number' => $this->organisation->inventoryStats->number_current_org_stock_families
                            ]

                        ],
                        [
                            'name'        => 'SKOs',
                            'icon'        => ['fal', 'fa-box'],
                            'description' => __('current'),
                            'route'       => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index',
                                'parameters' => $routeParameters
                            ],
                            'index'       => [
                                'number' => $this->organisation->inventoryStats->number_current_org_stocks
                            ],
                            'sub_data'    => $this->getDashboardStats()['stock']['cases']
                        ]
                    ]
                ],
                'statsBox' => [
                    [
                        'label' => __('Replenishments'),
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.index',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => 'fas fa-dolly-flatbed-empty',
                        'backgroundColor' => '#0ea5e911',
                        'color'           => '#0284c7ff',
                        'value' => $this->warehouse->stats->number_org_stocks_replenishments_wholesale,
                        'metaRight' => [
                            'tooltip' => __('Dropshipping replenishments'),
                            'icon'    => ['icon' => 'fas fa-shopping-basket', 'class' => 'mr-1'],
                            'count'   => $this->warehouse->stats->number_org_stocks_replenishments_dropshipping,
                            'route'   => [
                                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.replenishments.dropshipping',
                                'parameters' => $routeParameters
                            ],
                        ],
                    ],
                    [
                        'label' => __('Low Stock Audits'),
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.low_stock_audits.index',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => 'fal fa-clipboard-list-check',
                        'backgroundColor' => '#f59e0b11',
                        'color'           => '#d97706ff',
                        'value' => $this->warehouse->stats->number_org_stocks_low_stock_audits,
                        'editable' => [
                            'label'    => __('Threshold'),
                            'icon'     => 'fal fa-less-than-equal',
                            'field'    => 'low_stock_threshold',
                            'value'    => $this->warehouse->getLowStockThreshold(),
                            'title'    => __('Low stock threshold'),
                            'tooltip'  => __('SKOs with total stock in all locations below this threshold'),
                            'readonly' => !$this->canEdit,
                            'route'    => [
                                'name'       => 'grp.models.warehouse.low_stock_threshold.update',
                                'parameters' => ['warehouse' => $this->warehouse->id]
                            ],
                        ],
                    ],
                ],
                'additionalStatBox' => [
                    [
                        'label' => __('Negative Stocks'),
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => 'fal fa-exclamation-triangle',
                        'value' => LocationOrgStock::where('warehouse_id', $this->warehouse->id)->where('quantity', '<', 0)->count(),
                    ],
                    [
                        'label' => __('SKOs Without Product'),
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.orphan-product.current',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => 'fal fa-box',
                        'value' => $this->warehouse->stats->number_org_stocks_without_products,
                    ],
                ],
                // 'dashboardStats' => $this->getDashboardStats(),
                'dashboard'          => $this->getDashboard(),
                'stockHistoryToday'  => $this->getOrganisationStockHistoryData($this->organisation, $routeParameters),

            ]
        );
    }

    public function getDashboard(): array
    {
        $dashboard = [];
        foreach ($this->organisation->warehouses as $warehouse) {
            $utilization = 0;
            if ($warehouse->stats->number_locations) {
                $utilization = ($warehouse->stats->number_locations - $warehouse->stats->number_empty_locations) / $warehouse->stats->number_locations * 100;
            }
            $dashboard['columns'][] = [
                'widgets' => [
                    [
                        'label' => __($warehouse->name),
                        'type'  => 'stat_progress_card',
                        'data'  => [
                            'stockValue'  => $warehouse->stats->stock_value,
                            'utilization' => $utilization
                        ]
                    ]
                ]
            ];
        }

        return $dashboard;
    }

    public function getDashboardStats(): array
    {
        $stats = [];

        $stats['stock'] = [
            'label' => __('Stocks'),
            'count' => $this->organisation->inventoryStats->number_current_org_stocks
        ];

        foreach (OrgStockStateEnum::cases() as $case) {
            $count = OrgStockStateEnum::count($this->organisation)[$case->value];

            if ($case == OrgStockStateEnum::SUSPENDED && $count == 0) {
                continue;
            }


            $stats['stock']['cases'][] = [
                'value' => $case->value,
                'icon'  => OrgStockStateEnum::stateIcon()[$case->value],
                'count' => $count,
                'label' => OrgStockStateEnum::labels()[$case->value],
                'route' => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => request()->route()->originalParameters()['warehouse'],
                        'elements[state]'        => $case->value
                    ]
                ]
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
                            'label' => __('Inventory'),
                        ]
                    ]
                ]
            );
    }
}
