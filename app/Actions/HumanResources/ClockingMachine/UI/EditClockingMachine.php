<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:09:31 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditClockingMachine extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachine $clockingMachine): ClockingMachine
    {
        return $clockingMachine;
    }


    public function asController(Organisation $organisation, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->initialisation($organisation, $request);

        return $this->handle($clockingMachine);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inWorkplace(Organisation $organisation, Workplace $workplace, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->initialisation($organisation, $request);

        return $this->handle($clockingMachine);
    }


    /**
     * @throws \Exception
     */
    public function htmlResponse(ClockingMachine $clockingMachine, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('clocking machines'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $clockingMachine->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('edit clocking machine'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $clockingMachine->name
                                ],
                                'type' => [
                                    'type'    => 'select',
                                    'options' => Options::forEnum(ClockingMachineTypeEnum::class),
                                    'label'   => __('type'),
                                    'value'   => $clockingMachine->type
                                ],
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.clocking_machine..update',
                            'parameters' => $clockingMachine->id

                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowClockingMachine::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
