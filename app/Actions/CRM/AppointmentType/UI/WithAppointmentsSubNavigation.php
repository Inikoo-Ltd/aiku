<?php

namespace App\Actions\CRM\AppointmentType\UI;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;

trait WithAppointmentsSubNavigation
{
    public function getAppointmentsSubNavigation(Shop $shop): array
    {
        $routeParameters = [
            'organisation' => $shop->organisation->slug,
            'shop'         => $shop->slug,
        ];

        return [
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.appointments.index',
                    'parameters' => $routeParameters,
                ],
                'number'   => $shop->appointments()
                    ->whereIn('state', AppointmentStateEnum::holdingSlot())
                    ->where('ends_at', '>=', now())
                    ->count(),
                'label'    => __('Appointments'),
                'leftIcon' => [
                    'icon'    => 'fal fa-calendar-check',
                    'tooltip' => __('Upcoming appointments'),
                ],
            ],
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.appointments.types.index',
                    'parameters' => $routeParameters,
                ],
                'number'   => $shop->appointmentTypes()->count(),
                'label'    => __('Types'),
                'align'    => 'right',
                'leftIcon' => [
                    'icon'    => 'fal fa-calendar',
                    'tooltip' => __('Appointment types'),
                ],
            ],
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.appointments.staff.index',
                    'parameters' => $routeParameters,
                ],
                'number'   => DB::table('appointment_type_user')
                    ->join('appointment_types', 'appointment_types.id', '=', 'appointment_type_user.appointment_type_id')
                    ->where('appointment_types.shop_id', $shop->id)
                    ->whereNull('appointment_types.deleted_at')
                    ->distinct()
                    ->count('appointment_type_user.user_id'),
                'label'    => __('Staff'),
                'align'    => 'right',
                'leftIcon' => [
                    'icon'    => 'fal fa-user',
                    'tooltip' => __('Staff who arrange appointments'),
                ],
            ],
        ];
    }
}
