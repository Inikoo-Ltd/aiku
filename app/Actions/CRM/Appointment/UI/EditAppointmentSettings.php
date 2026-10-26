<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\CRM\AppointmentType\UI\WithAppointmentsSubNavigation;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAppointmentSettings extends OrgAction
{
    use WithAppointmentSettingsBlueprint;
    use WithAppointmentsSubNavigation;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $routeParameters = [
            'organisation' => $shop->organisation->slug,
            'shop'         => $shop->slug,
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Appointment settings'),
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'pageHead'    => [
                    'title'         => __('Appointment settings'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-sliders-h'],
                        'title' => __('Appointment settings'),
                    ],
                    'subNavigation' => $this->getAppointmentsSubNavigation($shop),
                ],
                'formData'    => [
                    'blueprint' => $this->appointmentSettingsBlueprint($shop, $request),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org.shop.update',
                            'parameters' => [
                                'organisation' => $shop->organisation_id,
                                'shop'         => $shop->id,
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.settings',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Settings'),
                    ],
                ],
            ]
        );
    }
}
