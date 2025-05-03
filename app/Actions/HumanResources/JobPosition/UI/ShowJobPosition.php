<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Jun 2023 11:39:33 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Employee\UI\IndexEmployees;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\JobPositionTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\EmployeesResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowJobPosition extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(JobPosition $jobPosition): JobPosition
    {
        return $jobPosition;
    }

    public function asController(Organisation $organisation, JobPosition $jobPosition, ActionRequest $request): JobPosition
    {
        $this->initialisation($organisation, $request)->withTab(JobPositionTabsEnum::values());
        return $this->handle($jobPosition);
    }

    public function htmlResponse(JobPosition $jobPosition, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/JobPosition',
            [
                'title'       => __('responsibility'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($jobPosition, $request),
                    'next'     => $this->getNext($jobPosition, $request),
                ],
                'pageHead'    => [
                    'title'     => $jobPosition->name,
                    'icon'      => ['fal', 'fa-clipboard-list-check'],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => JobPositionTabsEnum::navigation()
                ],

                JobPositionTabsEnum::SHOWCASE->value => $this->tab == JobPositionTabsEnum::SHOWCASE->value ?
                fn () => GetJobPositionShowcase::run($jobPosition)
                : Inertia::lazy(fn () => GetJobPositionShowcase::run($jobPosition)),

                JobPositionTabsEnum::EMPLOYEES->value       => $this->tab == JobPositionTabsEnum::EMPLOYEES->value ?
                fn () => EmployeesResource::collection(
                    IndexEmployees::run(
                        parent: $jobPosition,
                        prefix: JobPositionTabsEnum::EMPLOYEES->value
                    )
                )
                : Inertia::lazy(fn () => EmployeesResource::collection(
                    IndexEmployees::run(
                        parent: $jobPosition,
                        prefix: JobPositionTabsEnum::EMPLOYEES->value
                    )
                )),

                JobPositionTabsEnum::HISTORY->value => $this->tab == JobPositionTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($jobPosition))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($jobPosition)))
            ]
        )->table(
            IndexEmployees::make()->tableStructure(
                parent: $jobPosition,
                prefix: JobPositionTabsEnum::EMPLOYEES->value
            )
        );
    }


    public function jsonResponse(JobPosition $jobPosition): JobPositionResource
    {
        return new JobPositionResource($jobPosition);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {

        $jobPosition = JobPosition::where('slug', $routeParameters['jobPosition'])->first();
        return array_merge(
            (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.hr.job_positions.index',
                                'parameters' => ['organisation' => $this->organisation->slug]
                            ],
                            'label' => __('Responsibilities'),
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.hr.job_positions.show',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'jobPosition'  => $jobPosition->slug
                                ]
                            ],
                            'label' => $jobPosition->name,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(JobPosition $jobPosition, ActionRequest $request): ?array
    {
        $previous = JobPosition::where('slug', '<', $jobPosition->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(JobPosition $jobPosition, ActionRequest $request): ?array
    {
        $next = JobPosition::where('slug', '>', $jobPosition->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?JobPosition $jobPosition, string $routeName): ?array
    {
        if (!$jobPosition) {
            return null;
        }

        return match ($routeName) {
            'grp.org.hr.job_positions.show' => [
                'label' => $jobPosition->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $jobPosition->organisation->slug,
                        'jobPosition'  => $jobPosition->slug
                    ]
                ]
            ]
        };
    }
}
