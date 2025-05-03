<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Mar 2023 19:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\HumanResources\WithEmployeeSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\EmployeeTabsEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowEmployee extends OrgAction
{
    use WithActionButtons;
    use WithEmployeeSubNavigation;


    public function handle(Employee $employee): Employee
    {
        return $employee;
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): Employee
    {
        $this->initialisation($organisation, $request)->withTab(EmployeeTabsEnum::values());

        return $this->handle($employee);
    }

    public function htmlResponse(Employee $employee, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/HumanResources/Employee',
            [
                'title'       => __('employee'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    employee: $employee,
                    routeParameters: $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($employee, $request),
                    'next'     => $this->getNext($employee, $request),
                ],
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('Employee'),
                        'icon'  => 'fal fa-user-hard-hat'
                    ],
                    'model'     => __('Employee'),
                    'title'         => $employee->contact_name,
                    'subNavigation' => $this->getEmployeeSubNavigation($employee, $request),
                    'meta'          => [
                        [
                            'label'    => $employee->worker_number,
                            'key'      => 'worker_number',
                            'leftIcon' => [
                                'icon'    => 'fal fa-id-card',
                                'tooltip' => __('Worker number')
                            ]
                        ],
                    ],
                    'actions'       => [
                        $this->canEdit ? $this->getEditActionIcon($request) : null,
                    ],
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name' => 'grp.models.employee.attachment.attach',
                        'parameters' => [
                            'employee' => $employee->id,
                        ],
                        'method' => 'post'
                    ],
                    'detachRoute' => [
                        'name' => 'grp.models.employee.attachment.detach',
                        'parameters' => [
                            'employee' => $employee->id,
                        ],
                        'method' => 'delete'
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => EmployeeTabsEnum::navigation()
                ],

                EmployeeTabsEnum::SHOWCASE->value => $this->tab == EmployeeTabsEnum::SHOWCASE->value ?
                    fn () => GetEmployeeShowcase::run($employee)
                    : Inertia::lazy(fn () => GetEmployeeShowcase::run($employee)),
                EmployeeTabsEnum::HISTORY->value => $this->tab == EmployeeTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($employee))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($employee))),
                EmployeeTabsEnum::ATTACHMENTS->value => $this->tab == EmployeeTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($employee))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($employee))),


            ]
        )->table(
            IndexHistory::make()->tableStructure(prefix: EmployeeTabsEnum::HISTORY->value)
        )->table(IndexAttachments::make()->tableStructure(prefix: EmployeeTabsEnum::ATTACHMENTS->value));
    }


    public function getBreadcrumbs(Employee $employee, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.hr.employees.index',
                                'parameters' => array_merge(
                                    [
                                        '_query' => [
                                            'elements[state]' => 'working'
                                        ]
                                    ],
                                    Arr::only($routeParameters, 'organisation')
                                )
                            ],
                            'label' => __('Employees')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.hr.employees.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $employee->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Employee $employee, ActionRequest $request): ?array
    {
        $previous = Employee::where('slug', '<', $employee->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Employee $employee, ActionRequest $request): ?array
    {
        $next = Employee::where('slug', '>', $employee->slug)
            ->where('organisation_id', $this->organisation->id)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Employee $employee, string $routeName): ?array
    {
        if (!$employee) {
            return null;
        }

        return match ($routeName) {
            'grp.org.hr.employees.show' => [
                'label' => $employee->contact_name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'employee'     => $employee->slug
                    ]
                ]
            ]
        };
    }
}
