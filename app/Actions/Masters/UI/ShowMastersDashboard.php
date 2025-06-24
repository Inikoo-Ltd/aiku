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
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMastersDashboard extends OrgAction
{
    use WithMastersAuthorisation;


    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $request;
    }


    public function htmlResponse(): Response
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
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => 'Master Shops',
                            'icon'  => ['fal', 'fa-books'],
                            'route' => [
                                'name'       => 'grp.masters.master_shops.index',
                                'parameters' => []
                            ],
                            'index' => [
                                'number' => $this->group->goodsStats->number_master_shops
                            ]
                        ]
                    ]
                ],


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
