<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:48:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\InertiaAction;
use App\Http\Resources\CRM\AppointmentResource;
use App\Models\CRM\Appointment;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAppointment extends InertiaAction
{
    public function handle(Appointment $appointment): Appointment
    {
        return $appointment;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('crm.customers.edit');
        $this->canDelete = $request->user()->authTo('crm.customers.edit');

        return $request->user()->authTo("crm.customers.view");
    }

    public function asController(Shop $shop, Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisation($request)->withTab(AppointmentTabsEnum::values());

        return $this->handle($appointment);
    }

    public function htmlResponse(Appointment $appointment, ActionRequest $request): Response
    {
        return Inertia::render(
            'CRM/Appointment',
            [
                'title'       => __('appointment'),
                'breadcrumbs' => [],
//                    $this->getBreadcrumbs(
//                    $request->route()->getName(),
//                    $request->route()->originalParameters()
//                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($appointment, $request),
                    'next'     => $this->getNext($appointment, $request),
                ],
                'pageHead'    => [
                    'title'   => $appointment->name,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Appointment')
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $this->originalParameters
                            ]
                        ] : []
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AppointmentTabsEnum::navigation()
                ],
                'uploadRoutes' => [
                    'upload' => [
                        'name'       => 'org.models.customer.website.upload',
                        'parameters' => $appointment->slug
                    ],
                ],

                AppointmentTabsEnum::SHOWCASE->value => $this->tab == AppointmentTabsEnum::SHOWCASE->value ?
                    fn () => $appointment
                    : Inertia::lazy(fn () => $appointment),
            ]
        );
    }

    public function jsonResponse(Customer $customer): AppointmentResource
    {
        return new AppointmentResource($customer);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Appointment $appointment, array $routeParameters, string $suffix = null) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Customers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $appointment->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'org.crm.shop.appointments.show',
            'org.crm.shop.appointments.edit'
            => array_merge(
                ShowShop::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    Appointment::where('slug', $routeParameters['appointment'])->first(),
                    [
                        'index' => [
                            'name'       => 'org.crm.shop.appointments.index',
                            'parameters' => ['shop' => $routeParameters['shop']]
                        ],
                        'model' => [
                            'name'       => 'org.crm.shop.appointments.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
        };
    }


    public function getPrevious(Appointment $appointment, ActionRequest $request): ?array
    {
        $previous = Appointment::where('slug', '<', $appointment->slug)->when(true, function ($query) use ($appointment, $request) {
            if ($request->route()->getName() == 'org.shops.show.customers.show') {
                $query->where('customers.shop_id', $appointment->shop_id);
            }
        })->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Appointment $appointment, ActionRequest $request): ?array
    {
        $next = Appointment::where('slug', '>', $appointment->slug)->when(true, function ($query) use ($appointment, $request) {
            if ($request->route()->getName() == 'org.shops.show.customers.show') {
                $query->where('customers.shop_id', $appointment->shop_id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Appointment $appointment, string $routeName): ?array
    {
        if (!$appointment) {
            return null;
        }

        return match ($routeName) {
            'org.crm.appointments.show' => [
                'label' => $appointment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'customer' => $appointment->slug
                    ]
                ]
            ],
            'org.crm.shop.appointments.show' => [
                'label' => $appointment->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'     => $appointment->shop->slug,
                        'customer' => $appointment->slug
                    ]
                ]
            ]
        };
    }
}
