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
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
        $title = __('Human Resources');

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
                        'name'  => __('Employees'),
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
                        'name'  => __('Working places'),
                        'stat'  => $this->organisation->humanResourcesStats->number_workplaces,
                        'route' => [
                            'name'       => 'grp.org.hr.workplaces.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'  => __('Responsibilities'),
                        'stat'  => $this->organisation->humanResourcesStats->number_job_positions,
                        'route' => [
                            'name'       => 'grp.org.hr.job_positions.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ]
                ],
                'orgChartNodes' => $this->getOrgChartNodes(),

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

    private function getOrgChartNodes(): array
    {
        $employees = $this->organisation->employees()
            ->select(['id', 'contact_name', 'alias', 'job_title', 'slug', 'image_id'])
            ->with('image')
            ->orderBy('contact_name')
            ->orderBy('alias')
            ->get();

        $jobGroups = [];

        foreach ($employees as $employee) {
            $jobTitle = trim((string)$employee->job_title);
            $jobName  = $jobTitle !== '' ? $jobTitle : __('Unassigned');
            $groupKey = Str::lower($jobName);

            if (!isset($jobGroups[$groupKey])) {
                $jobSlug            = Str::slug($jobName);
                $jobGroups[$groupKey] = [
                    'id'      => 'job-'.($jobSlug !== '' ? $jobSlug : substr(md5($groupKey), 0, 10)),
                    'name'    => $jobName,
                    'title'   => __('Job Position'),
                    'reports' => [],
                ];
            }

            $jobGroups[$groupKey]['reports'][] = [
                'id'      => "employee-$employee->id",
                'name'    => $employee->contact_name ?: ($employee->alias ?: $employee->slug),
                'title'   => __('Employee'),
                'avatarUrl' => Arr::get(
                    $employee->imageSources(120, 120),
                    'original',
                    'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode((string)$employee->slug)
                ),
                'reports' => [],
            ];
        }

        ksort($jobGroups, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($jobGroups as &$jobGroup) {
            usort($jobGroup['reports'], fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        }
        unset($jobGroup);

        return array_values($jobGroups);
    }
}
