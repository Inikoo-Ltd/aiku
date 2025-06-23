<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:06:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMastersDashboard extends OrgAction
{
    use WithMastersAuthorisation;


    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }


    public function htmlResponse(Group $group): Response
    {
        // $timesUpdate = ['1d', '1w', '1m', '1y', 'all'];

        // $topFamily     = [];
        // $topDepartment = [];
        // $topProduct    = [];

        // foreach ($timesUpdate as $timeUpdate) {
        //     $family = $group->stats->{'top'.$timeUpdate.'Family'};

        //     $topFamily[$timeUpdate] = $family ? FamilyResource::make($family) : null;

        //     $department                 = $group->stats->{'top'.$timeUpdate.'Department'};
        //     $topDepartment[$timeUpdate] = $department ? DepartmentResource::make($department) : null;

        //     $product                 = $group->stats->{'top'.$timeUpdate.'Product'};
        //     $topProduct[$timeUpdate] = $product ? ProductResource::make($product) : null;
        // }
        return Inertia::render(
            'Masters/MastersDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('masters'),
                'pageHead'     => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-ruler-combined'],
                        'title' => __('masters')
                    ],
                    'title' => __('master catalogue'),
                ],
                // 'flatTreeMaps' => [
                //     [
                //         [
                //             'name'  => 'Master Shops',
                //             'icon'  => ['fal', 'fa-books'],
                //             'route' => [
                //                 'name'       => 'grp.masters.master_shops.index',
                //                 'parameters' => []
                //             ],
                //             'index' => [
                //                 'number' => $this->group->goodsStats->number_master_shops
                //             ]
                //         ]
                //     ]
                // ],
                'stats' => [
                    [
                        'label' => __('Master Shops'),
                        // 'route' => [
                        //     'name'       => 'grp.masters.master_shops.index',
                        //     'parameters' => []
                        // ],
                        // 'icon'  => 'fal fa-store',
                        "color" => "#a3e635",
                        'value' => $group->stats->number_master_shops,
                    ],
                    [
                        'label' => __('Master Departments'),
                        // 'route' => [
                        //     'name'       => 'grp.masters.departments.index',
                        //     'parameters' => []
                        // ],
                        // 'icon'  => 'fal fa-folder-tree',
                        "color" => "#a3e635",
                        'value' => $group->stats->number_master_product_categories_type_department,

                        'metaRight'  => [
                            'tooltip' => __('Sub Departments'),
                            'icon'    => [
                                'icon'  => 'fal fa-folder-tree',
                                'class' => ''
                            ],
                            'count'   => $group->stats->number_master_product_categories_type_department_sub_departments,
                        ],
                        'metas' => [
                            [
                                'tooltip' => __('Active departments'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $group->stats->number_current_master_product_categories_type_department,
                                // 'route' => [
                                //     'name'       => 'grp.masters.departments.index',
                                //     'parameters' => [
                                //         'index_elements[state]' => 'active'
                                //     ]
                                // ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Master Families'),
                        // 'route' => [
                        //     'name'       => 'grp.masters.families.index',
                        //     'parameters' => []
                        // ],
                        // 'icon'  => 'fal fa-folder',
                        "color" => "#e879f9",
                        'value' => 0,//$group->productCategories->where('type', 'family')->count(),
                        'metas' => [
                            [
                                'tooltip' => __('Active families'),
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                'count'   => $group->stats->number_current_master_product_categories_type_family,
                                // 'route' => [
                                //     'name'       => 'grp.masters.families.index',
                                //     'parameters' => [
                                //         'index_elements[state]' => 'active'
                                //     ]
                                // ],
                            ],
                        ]
                    ],
                    [
                        'label' => __('Master Products'),
                        // 'route' => [
                        //     'name'       => 'grp.masters.products.index',
                        //     'parameters' => []
                        // ],
                        // 'icon'  => 'fal fa-cube',
                        "color" => "#38bdf8",
                        'value' => 0,//$group->masterAssets->count(),
                        'metas' => [
                            [
                                "icon"    => [
                                    "tooltip" => "active",
                                    "icon"    => "fas fa-check-circle",
                                    "class"   => "text-green-500"
                                ],
                                "count"   => $group->stats->number_current_master_assets_type_product,
                                "tooltip" => "Active",
                                // 'route' => [
                                //     'name'       => 'grp.masters.products.index',
                                //     'parameters' => [
                                //         'index_elements[state]' => 'active'
                                //     ]
                                // ],
                            ],
                        ]
                    ],
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
                                'name' => 'grp.masters.dashboard'
                            ],
                            'label' => __('Masters'),
                        ]
                    ]
                ]
            );
    }


}
