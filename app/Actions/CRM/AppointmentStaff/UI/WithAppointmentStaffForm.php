<?php

namespace App\Actions\CRM\AppointmentStaff\UI;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;

trait WithAppointmentStaffForm
{
    protected function appointmentTypesField(Shop $shop, ?User $user = null): array
    {
        return [
            'type'        => 'select',
            'mode'        => 'multiple',
            'label'       => __('Appointments they arrange'),
            'information' => __('They are told about every booking of these appointments.'),
            'required'    => true,
            'searchable'  => true,
            'labelProp'   => 'name',
            'valueProp'   => 'id',
            'options'     => $shop->appointmentTypes()->orderBy('name')->get(['id', 'name'])->toArray(),
            'value'       => $user
                ? $shop->appointmentTypes()
                    ->whereHas('attendees', fn ($query) => $query->where('users.id', $user->id))
                    ->pluck('appointment_types.id')
                    ->all()
                : [],
        ];
    }
}
