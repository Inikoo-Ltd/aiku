<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 11 Apr 2023 08:24:46 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\Clocking\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateClocking extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('new clocking'),
                'pageHead' => [
                    'title'        => __('new clocking'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => 'grp.org.hr.workplaces.show.clockings.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('id'),
                            'fields' => [
                                'generator_id' => [
                                    'type'        => 'select',
                                    'label'       => __('employee'),
                                    'placeholder' => __('Select a employee'),
                                    'options'     => Options::forModels(Employee::class, 'contact_name', 'id'),
                                    'required'    => true,
                                    'searchable'  => true
                                ],
                                'date' => [
                                    'type'     => 'date',
                                    'label'    => __('date'),
                                    'required' => true
                                ],
                                'time' => [
                                    'type'     => 'time',
                                    'label'    => __('time'),
                                    'required' => true
                                ],
                            ]
                        ],


                    ],
                    'route' => match ($request->route()->getName()) {
                        'grp.org.hr.workplaces.show.clockings.create' => [
                            'name'      => 'grp.models.workplace.clocking.store',
                            'arguments' => [$request->route()->originalParameters()['workplace']->slug]
                        ],
                        default => [
                            'name'      => 'grp.models.clocking-machine.clocking.store',
                            'arguments' => [
                                $request->route()->originalParameters()['clockingMachine']->slug
                            ]
                        ]
                    }
                ],

            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWorkplaceInClockingMachine(Organisation $organisation, Workplace $workplace, ClockingMachine $clockingMachine, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexClockings::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating clocking'),
                    ]
                ]
            ]
        );
    }
}
