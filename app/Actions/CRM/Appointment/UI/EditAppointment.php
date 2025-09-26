<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Oct 2023 22:43:44 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\InertiaAction;
use App\Enums\CRM\Appointment\AppointmentEventEnum;
use App\Enums\CRM\Appointment\AppointmentTypeEnum;
use App\Models\CRM\Appointment;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditAppointment extends InertiaAction
{
    public function handle(Appointment $appointment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'    => __('Edit appointment'),
                'pageHead' => [
                    'title' => __('Edit appointment'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-handshake'],
                        'title' => __('Appointment')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'back',
                            'label' => __('back'),
                            'route' => [
                                'name' => match ($request->route()->getName()) {
                                    'shops.show.appointments.edit' => 'org.shops.appointments.index',
                                    default                        => preg_replace('/edit$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('customer'),
                                'fields' => [
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('appointment name'),
                                        'required' => true,
                                        'value'    => $appointment->name
                                    ],
                                    'customer_id' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('customer'),
                                        'required' => true,
                                        'value'    => $appointment->customer_id,
                                        'options'  => GetCustomerOptions::run(Customer::all())
                                    ],
                                    'type' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'value'    => $appointment->type,
                                        'options'  => Options::forEnum(AppointmentTypeEnum::class)
                                    ],
                                    'event' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('event'),
                                        'required' => true,
                                        'value'    => $appointment->event,
                                        'options'  => Options::forEnum(AppointmentEventEnum::class)
                                    ],
                                    'event_address' => [
                                        'type'     => 'input',
                                        'label'    => __('event address'),
                                        'value'    => $appointment->event_address,
                                        'required' => true,
                                    ],
                                    'schedule_at' => [
                                        'type'     => 'date',
                                        'label'    => __('schedule'),
                                        'value'    => $appointment->schedule_at,
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'  => 'textarea',
                                        'label' => __('description'),
                                        'value' => $appointment->description
                                    ]
                                ]
                            ],
                        ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'org.models.appointment.update',
                            'parameters' => [$appointment->slug]
                        ],
                    ]
                ]
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('crm.edit');
    }

    public function asController(Shop $shop, Appointment $appointment, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($appointment, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs(
                routeName: preg_replace('/edit$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'editingModel',
                    'editingModel' => [
                        'label' => __('Editing appointment'),
                    ]
                ]
            ]
        );
    }
}
