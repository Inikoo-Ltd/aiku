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

class EditAppointmentStaff extends OrgAction
{
    use WithCRMAuthorisation;
    use WithAppointmentStaffForm;

    public function handle(User $user): User
    {
        return $user;
    }

    public function asController(Organisation $organisation, Shop $shop, User $user, ActionRequest $request): User
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($user);
    }

    public function htmlResponse(User $user, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit appointment staff'),
                'breadcrumbs' => $this->getBreadcrumbs($user, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $user->chatName(),
                    'model'   => __('Appointment staff'),
                    'icon'    => [
                        'title' => __('Staff who arrange appointments'),
                        'icon'  => 'fal fa-user',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.appointments.staff.index',
                                'parameters' => [
                                    'organisation' => $this->shop->organisation->slug,
                                    'shop'         => $this->shop->slug,
                                ],
                            ],
                        ],
                        [
                            'type'  => 'button',
                            'style' => 'delete',
                            'key'   => 'delete',
                            'label' => __('Remove from appointments'),
                            'route' => [
                                'name'       => 'grp.models.shop.appointment_staff.delete',
                                'parameters' => ['shop' => $this->shop->id, 'user' => $user->id],
                                'method'     => 'delete',
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Appointments'),
                            'label'  => __('Appointments'),
                            'icon'   => 'fal fa-calendar',
                            'fields' => [
                                'appointment_types' => $this->appointmentTypesField($this->shop, $user),
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.appointment_staff.update',
                            'parameters' => ['shop' => $this->shop->id, 'user' => $user->id],
                        ],
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(User $user, array $routeParameters): array
    {
        return array_merge(
            IndexAppointmentStaff::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.appointments.staff.edit',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $user->chatName(),
                    ],
                    'suffix' => '('.__('Editing').')',
                ],
            ]
        );
    }
}
