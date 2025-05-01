<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\HumanResources;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowHumanResourcesDashboard extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('human resources');

        return Inertia::render(
            'Org/HumanResources/HumanResourcesDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => $title
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => $title
                    ],
                    'title'     => $title,
                ],
                'stats'       => [
                    [
                        'name'  => __('employees'),
                        'stat'  => $this->organisation->humanResourcesStats->number_employees_state_working,
                        'route' => [
                            'name'       => 'grp.org.hr.employees.index',
                            'parameters' => array_merge(
                                [
                                    '_query' => [
                                        'elements[state]' => 'working'
                                    ]
                                ],
                                $request->route()->originalParameters()
                            )
                        ]
                    ],
                    [
                        'name'  => __('working places'),
                        'stat'  => $this->organisation->humanResourcesStats->number_workplaces,
                        'route' => [
                            'name'       => 'grp.org.hr.workplaces.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'  => __('responsibilities'),
                        'stat'  => $this->organisation->humanResourcesStats->number_job_positions,
                        'route' => [
                            'name'       => 'grp.org.hr.job_positions.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.hr.dashboard',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Human resources'),
                        ]
                    ]
                ]
            );
    }
}
