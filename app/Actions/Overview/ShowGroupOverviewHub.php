<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 21:37:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Overview;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowGroupOverviewHub extends GrpAction
{
    use WithGroupOverviewAuthorisation;

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation(app('group'), $request);
        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Overview/OverviewHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('overview'),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-mountains'],
                        'title' => __('overview')
                    ],
                    'title'     => __('overview'),
                ],
                'dashboard_stats' => [
                    'setting' => [
                        "currency_chosen" => $this->group->currency->code
                    ],
                    'widgets' => [

                            'column_count' => 2,
                            'components' => [
                                [
                                    'col_span' => 1,
                                    'row_span' => 10,
                                    'type' => 'overview_display',
                                    'data' => GetGroupOverview::run($this->group)
                                ],
                                [
                                    'col_span' => 1,
                                    'type' => 'operation_display',

                                ],
                                [
                                    'col_span' => 1,
                                    'type' => 'operation_display',

                                ]
                            ],
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
                                'name' => 'grp.overview.hub'
                            ],
                            'label'  => __('Overview'),
                        ]
                    ]
                ]
            );
    }
}
