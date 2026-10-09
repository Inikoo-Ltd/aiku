<?php

namespace App\Actions\CRM\AppointmentType\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAppointmentType extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentTypeForm;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('New appointment type'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'model'   => __('Appointment type'),
                    'title'   => __('Create'),
                    'icon'    => [
                        'title' => __('Appointment types'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.types.index',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->appointmentTypeFormBlueprint($shop),
                    'route'     => [
                        'name'       => 'grp.models.shop.appointment_type.store',
                        'parameters' => ['shop' => $shop->id],
                    ],
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexAppointmentTypes::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating appointment type'),
                    ],
                ],
            ]
        );
    }
}
