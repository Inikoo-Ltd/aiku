<?php

namespace App\Actions\CRM\AppointmentType\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAppointmentType extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentTypeForm;

    public function handle(AppointmentType $appointmentType): AppointmentType
    {
        return $appointmentType;
    }

    public function asController(Organisation $organisation, Shop $shop, AppointmentType $appointmentType, ActionRequest $request): AppointmentType
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($appointmentType);
    }

    public function htmlResponse(AppointmentType $appointmentType, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit appointment type'),
                'breadcrumbs' => $this->getBreadcrumbs($appointmentType, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $appointmentType->name,
                    'model'   => __('Edit appointment type'),
                    'icon'    => [
                        'title' => __('Appointment types'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.types.index',
                                'parameters' => [
                                    'organisation' => $appointmentType->organisation->slug,
                                    'shop'         => $appointmentType->shop->slug,
                                ],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->appointmentTypeFormBlueprint($appointmentType->shop, $appointmentType),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.appointment_type.update',
                            'parameters' => ['appointmentType' => $appointmentType->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(AppointmentType $appointmentType, array $routeParameters): array
    {
        return array_merge(
            IndexAppointmentTypes::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.types.edit',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $appointmentType->name,
                    ],
                    'suffix' => '('.__('Editing').')',
                ],
            ]
        );
    }
}
