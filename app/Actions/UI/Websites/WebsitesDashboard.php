<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 23:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Websites;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class WebsitesDashboard extends OrgAction
{
    public function handle($scope)
    {
        return $scope;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('websites.view');
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }

    public function htmlResponse(Organisation $organisation): Response
    {

        return Inertia::render(
            'Web/WebsitesDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('websites dashboard'),
                'pageHead' => [
                    'title' => __('websites dashboard'),
                ],
                'flatTreeMaps' => [
                    [

                        [
                            'name' => __('websites'),
                            'icon' => ['fal', 'fa-globe'],
                            'route' => ['grp.org.shops.show.web.websites.index'],
                            'index' => [
                                'number' => $organisation->webStats->number_websites,
                            ],

                        ],

                    ],
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
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.web.dashboard',
                            ],
                            'label' => __('websites dashboard'),
                        ],
                    ],
                ]
            );
    }
}
