<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 22:44:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Overview;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\UI\ShowOrganisationDashboard;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrganisationOverviewHub extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->organisation = $organisation;
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Overview/OverviewHub',
            [
                'breadcrumbs'     => $this->getBreadcrumbs(
                    $routeParameters
                ),
                'title'           => __('overview'),
                'pageHead'        => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-mountains'],
                        'title' => __('overview')
                    ],
                    'title' => __('overview'),
                ],
                'dashboard_stats' => [
                    'setting' => [
                        "currency_chosen" => 'usd' // | pounds | dollar
                    ],
                    'widgets' => [
                        'column_count' => 2,
                        'components'   => [
                            [
                                'col_span' => 1,
                                'row_span' => 10,
                                'type'     => 'overview_display',
                                'data'     => GetOrganisationOverview::run($this->organisation)
                            ],
                            [
                                'col_span' => 1,
                                'type'     => 'operation_display',

                            ],
                            [
                                'col_span' => 1,
                                'type'     => 'operation_display',

                            ]

                        ]
                    ],
                ],
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
                                'name'       => 'grp.org.overview.hub',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Overview'),
                        ]
                    ]
                ]
            );
    }
}
