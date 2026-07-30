<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 14:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditClockingMachineQRCode extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineQRCode $clockingMachineQRCode): ClockingMachineQRCode
    {
        return $clockingMachineQRCode;
    }

    public function asController(Organisation $organisation, ClockingMachine $clockingMachine, ClockingMachineQRCode $clockingMachineQRCode, ActionRequest $request): ClockingMachineQRCode
    {
        $this->initialisation($organisation, $request);

        return $this->handle($clockingMachineQRCode);
    }

    public function htmlResponse(ClockingMachineQRCode $clockingMachineQRCode, ActionRequest $request): Response
    {
        $blueprint = [
            [
                'title'  => __('Edit QR code'),
                'label'  => __('Basic Setting'),
                'fields' => [
                    'label'  => [
                        'type'  => 'input',
                        'label' => __('Label'),
                        'value' => $clockingMachineQRCode->label,
                    ],
                    'active' => [
                        'type'  => 'toggle',
                        'label' => __('Active'),
                        'value' => (bool) $clockingMachineQRCode->active,
                    ],
                ],
            ],
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('QR code'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $clockingMachineQRCode->label ?? $clockingMachineQRCode->hash,
                    'model'   => __('QR code'),
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'secondary',
                            'icon'    => 'fal fa-sync',
                            'label'   => __('Generate new hash'),
                            'tooltip' => __('Issue a new hash, every existing copy of this QR code stops working'),
                            'route'   => [
                                'name'       => 'grp.models.clocking_machine_qr_code.hash.regenerate',
                                'parameters' => ['clockingMachineQRCode' => $clockingMachineQRCode->id],
                                'method'     => 'patch'
                            ]
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.hr.clocking_machines.show',
                                'parameters' => [
                                    'organisation'    => $request->route()->originalParameters()['organisation'],
                                    'clockingMachine' => $request->route()->originalParameters()['clockingMachine'],
                                ]
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $blueprint,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.clocking_machine_qr_code.update',
                            'parameters' => $clockingMachineQRCode->id
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowClockingMachine::make()->getBreadcrumbs(
            routeName: 'grp.org.hr.clocking_machines.show',
            routeParameters: $routeParameters,
            suffix: '('.__('Editing QR code').')'
        );
    }
}
