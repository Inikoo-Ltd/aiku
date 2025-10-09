<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Oct 2023 22:43:44 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\CRM\Customer\UI\GetCustomerOptions;
use App\Actions\InertiaAction;
use App\Enums\CRM\Appointment\AppointmentEventEnum;
use App\Enums\CRM\Appointment\AppointmentTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateAppointment extends InertiaAction
{
    public function handle(Organisation|Shop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => __('New appointment'),
                'pageHead'    => [
                    'title'   => __('New appointment'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-handshake'],
                        'title' => __('Appointment')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => match ($request->route()->getName()) {
                                    'shops.show.appointments.create' => 'org.shops.appointments.index',
                                    default                          => preg_replace('/create$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('customer'),
                                'fields' => [
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('appointment name'),
                                        'required' => true,
                                    ],
                                    'customer_id' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('customer'),
                                        'required' => true,
                                        'options'  => GetCustomerOptions::run(Customer::all())
                                    ],
                                    'type' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(AppointmentTypeEnum::class)
                                    ],
                                    'event' => [
                                        'type'     => 'select',
                                        'mode'     => 'single',
                                        'label'    => __('event'),
                                        'required' => true,
                                        'options'  => Options::forEnum(AppointmentEventEnum::class)
                                    ],
                                    'event_address' => [
                                        'type'     => 'input',
                                        'label'    => __('event address'),
                                        'value'    => '',
                                        'required' => true,
                                    ],
                                    'schedule_at' => [
                                        'type'     => 'date',
                                        'label'    => __('schedule'),
                                        'value'    => '',
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'value'    => ''
                                    ]
                                ]
                            ],

                        ],
                    'route'     =>
                        match(class_basename($parent)) {
                            'Shop' => [
                                'name'       => 'org.models.shop.appointment.store',
                                'parameters' => [$parent->id]
                            ],
                            default => [
                                [
                                    'name'      => 'org.models.appointment.store',
                                ]
                            ]
                        }
                ]

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('crm.edit');
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);
        return $this->handle(organisation(), $request);
    }

    public function inShop(Shop $shop, ActionRequest $request): Response
    {
        $this->initialisation($request);
        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating appointment'),
                    ]
                ]
            ]
        );
    }
}
