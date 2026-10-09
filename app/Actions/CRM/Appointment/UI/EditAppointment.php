<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAppointment extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentForm;

    public function handle(Appointment $appointment): Appointment
    {
        return $appointment;
    }

    public function asController(Organisation $organisation, Shop $shop, Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($appointment);
    }

    public function htmlResponse(Appointment $appointment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Appointment'),
                'breadcrumbs' => $this->getBreadcrumbs($appointment, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $appointment->contact_name,
                    'model'   => __('Appointment'),
                    'icon'    => [
                        'title' => __('Appointments'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.show',
                                'parameters' => [
                                    'organisation' => $this->shop->organisation->slug,
                                    'shop'         => $this->shop->slug,
                                    'appointment'  => $appointment->id,
                                ],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Status'),
                            'label'  => __('Status'),
                            'icon'   => 'fal fa-calendar-check',
                            'fields' => [
                                'state' => $this->appointmentStateField($appointment),
                            ],
                        ],
                        [
                            'title'  => __('When'),
                            'label'  => __('When'),
                            'icon'   => 'fal fa-clock',
                            'fields' => $this->appointmentWhenFields($this->shop, $appointment),
                        ],
                        [
                            'title'  => __('Visitor'),
                            'label'  => __('Visitor'),
                            'icon'   => 'fal fa-user',
                            'fields' => $this->appointmentVisitorFields($appointment),
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.appointment.update',
                            'parameters' => ['appointment' => $appointment->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(Appointment $appointment, array $routeParameters): array
    {
        return array_merge(
            IndexAppointments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.edit',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $appointment->contact_name,
                    ],
                ],
            ]
        );
    }
}
