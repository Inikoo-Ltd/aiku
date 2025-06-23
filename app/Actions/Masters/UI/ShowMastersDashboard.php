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
                'stats' => [
                    [
                        'label' => __('Master Shops'),
                        'route' => [
                            'name'       => 'grp.masters.master_shops.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-store',
                        "color" => "#facc15",
                        'value' => $group->goodsStats->number_current_master_shops,
                    ],
                    [
                        'label' => __('Master Departments'),
                        'route' => [
                            'name'       => 'grp.masters.master_departments.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-folder-tree',
                        "color" => "#a3e635",
                        'value' => $group->goodsStats->number_current_master_product_categories_type_department,

                        'metaRight'  => [
                            'tooltip' => __('Master Sub Departments'),
                            'icon'    => [
                                'icon'  => 'fal fa-folder-tree',
                                'class' => ''
                            ],
                            'count'   => $group->goodsStats->number_master_product_categories_type_department_sub_departments,
                        ],
                    ],
                    [
                        'label' => __('Master Families'),
                        'route' => [
                            'name'       => 'grp.masters.master_families.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-folder',
                        "color" => "#e879f9",
                        'value' => $group->goodsStats->number_current_master_product_categories_type_family,
                    ],
                    [
                        'label' => __('Master Products'),
                        'route' => [
                            'name'       => 'grp.masters.master_products.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-cube',
                        "color" => "#38bdf8",
                        'value' => $group->goodsStats->number_current_master_assets_type_product,
                    ],
                    [
                        'label' => __('Master Collections'),
                        'route' => [
                            'name'       => 'grp.masters.master_collections.index',
                            'parameters' => []
                        ],
                        'icon'  => 'fal fa-album-collection',
                        "color" => "#4f46e5",
                        'value' => $group->goodsStats->number_current_master_collections,
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
