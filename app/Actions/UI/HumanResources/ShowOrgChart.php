<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\HumanResources;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgChart extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Org chart');

        return Inertia::render(
            'Org/HumanResources/OrgChart',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => $title,
                'pageHead'      => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => $title,
                    ],
                    'iconRight' => [
                        'icon'    => ['fal', 'fa-user-hard-hat'],
                        'tooltip' => __('Human Resources'),
                        'url'     => [
                            'name'       => 'grp.org.hr.dashboard',
                            'parameters' => $request->route()->originalParameters(),
                        ],
                    ],
                    'title'     => $title,
                ],
                'orgChartNodes' => $this->getOrgChartNodes(),
            ]
        );
    }

    public function getBreadcrumbs($routeParameters): array
    {
        return array_merge(
            ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.hr.org_chart',
                            'parameters' => Arr::only($routeParameters, 'organisation'),
                        ],
                        'label' => __('Org chart'),
                    ],
                ],
            ]
        );
    }

    private function getOrgChartNodes(): array
    {
        $employees = $this->organisation->employees()
            ->where('state', '!=', EmployeeStateEnum::LEFT->value)
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
                $jobSlug              = Str::slug($jobName);
                $jobGroups[$groupKey] = [
                    'id'      => 'job-'.($jobSlug !== '' ? $jobSlug : substr(md5($groupKey), 0, 10)),
                    'name'    => $jobName,
                    'title'   => __('Job Position'),
                    'reports' => [],
                ];
            }

            $jobGroups[$groupKey]['reports'][] = [
                'id'        => "employee-$employee->id",
                'name'      => $employee->contact_name ?: ($employee->alias ?: $employee->slug),
                'title'     => __('Employee'),
                'avatarUrl' => Arr::get(
                    $employee->imageSources(120, 120),
                    'original',
                    'https://api.dicebear.com/7.x/avataaars/svg?seed='.rawurlencode((string)$employee->slug)
                ),
                'reports'   => [],
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
