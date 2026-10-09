<?php

namespace App\Actions\CRM\AppointmentStaff\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAppointmentStaff extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentStaffForm;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('Add appointment staff'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'model'   => __('Appointment staff'),
                    'title'   => __('Add'),
                    'icon'    => [
                        'title' => __('Staff who arrange appointments'),
                        'icon'  => 'fal fa-user',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.staff.index',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Staff who arranges appointments'),
                            'fields' => [
                                'user_id'           => [
                                    'type'       => 'select',
                                    'label'      => __('User'),
                                    'required'   => true,
                                    'searchable' => true,
                                    'labelProp'  => 'name',
                                    'valueProp'  => 'id',
                                    'options'    => User::where('group_id', $shop->group_id)
                                        ->where('status', true)
                                        ->orderBy('contact_name')
                                        ->get(['id', 'contact_name', 'username'])
                                        ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()]),
                                    'value'      => null,
                                ],
                                'appointment_types' => $this->appointmentTypesField($shop),
                            ],
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.shop.appointment_staff.store',
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
            IndexAppointmentStaff::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Adding staff'),
                    ],
                ],
            ]
        );
    }
}
