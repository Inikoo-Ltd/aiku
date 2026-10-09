<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\AppointmentResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAppointment extends OrgAction
{
    use WithCRMAuthorisation;

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
        $appointment->loadMissing(['appointmentType', 'visitor', 'user', 'createdBy', 'shop.timezone']);

        $actions = [];
        if ($this->canEdit) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.appointments.edit',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Appointment',
            [
                'title'       => __('Appointment'),
                'breadcrumbs' => $this->getBreadcrumbs($appointment, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $appointment->contact_name,
                    'model'   => __('Appointment'),
                    'icon'    => [
                        'title' => __('Appointment'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => $actions,
                ],
                'appointment' => AppointmentResource::make($appointment)->resolve(),
                'canEdit'     => $this->canEdit,
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
                            'name'       => 'grp.org.shops.show.crm.appointments.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $appointment->contact_name,
                    ],
                ],
            ]
        );
    }
}
