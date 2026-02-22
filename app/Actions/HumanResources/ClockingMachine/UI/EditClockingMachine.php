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
        $blueprint = [
            [
                'title'  => __('Edit clocking machine'),
                'label'  => __('Basic Setting'),
                'fields' => [
                    'name' => [
                        'type'  => 'input',
                        'label' => __('Name'),
                        'value' => $clockingMachine->name,
                    ],
                    'type' => [
                        'type'    => 'select',
                        'options' => Options::forEnum(ClockingMachineTypeEnum::class),
                        'label'   => __('Type'),
                        'value'   => $clockingMachine->type,
                    ],
                ],
            ],
        ];
        if ($clockingMachine->type === ClockingMachineTypeEnum::QR_CODE->value) {
            $blueprint[] = [
                'title'  => __('QR Configuration'),
                'label'  => __('QR Settings'),
                'fields' => [
                    'config.qr.enable' => [
                        'type'  => 'toggle',
                        'label' => __('Enable QR Code'),
                        'value' => (bool) data_get($clockingMachine->config, 'qr.enable', false),
                    ],
                    'config.qr.refresh_interval' => [
                        'type'  => 'input_number',
                        'label' => __('Refresh Interval (seconds)'),
                        'value' => data_get($clockingMachine->config, 'qr.refresh_interval', 60),
                    ],
                    'config.qr.expiry_duration' => [
                        'type'  => 'input_number',
                        'label' => __('Expiry Duration (seconds)'),
                        'value' => data_get($clockingMachine->config, 'qr.expiry_duration', 60),
                    ],
                    'config.qr.allow_multiple_scans' => [
                        'type'  => 'toggle',
                        'label' => __('Allow Multiple Scans'),
                        'value' => (bool) data_get($clockingMachine->config, 'qr.allow_multiple_scans', false),
                    ],
                    'config.qr.allow_coordinates' => [
                        'type'  => 'toggle',
                        'label' => __('Allow Coordinates Matching'),
                        'value' => (bool) data_get($clockingMachine->config, 'qr.allow_coordinates', false),
                    ],
                    'config.qr.coordinates' => [
                        'type'  => 'map-picker',
                        'label' => __('Maps Coordinates'),
                        'value' => data_get($clockingMachine->config, 'qr.coordinates'),
                    ],
                    'config.qr.radius' => [
                        'type'  => 'input_number',
                        'label' => __('Radius (meters)'),
                        'value' => data_get($clockingMachine->config, 'qr.radius'),
                    ],
                ],
            ];
        }

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
                    'blueprint' => $blueprint,
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
            suffix: '(' . __('Editing') . ')'
        );
    }
}
