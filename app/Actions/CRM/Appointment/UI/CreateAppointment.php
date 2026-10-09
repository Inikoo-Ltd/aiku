<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAppointment extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentForm;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('New appointment'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'model'   => __('Appointment'),
                    'title'   => __('New'),
                    'icon'    => [
                        'title' => __('Appointments'),
                        'icon'  => 'fal fa-calendar',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.index',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('When'),
                            'fields' => $this->appointmentWhenFields($shop),
                        ],
                        [
                            'title'  => __('Visitor'),
                            'fields' => $this->appointmentVisitorFields(),
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.shop.appointment.store',
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
            IndexAppointments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('New appointment'),
                    ],
                ],
            ]
        );
    }
}
