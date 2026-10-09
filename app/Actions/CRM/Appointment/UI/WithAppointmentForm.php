<?php

namespace App\Actions\CRM\Appointment\UI;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\User;

trait WithAppointmentForm
{
    protected function appointmentWhenFields(Shop $shop, ?Appointment $appointment = null): array
    {
        $timezone = $shop->timezone?->name ?? 'UTC';

        return [
            'appointment_type_id' => [
                'type'      => 'select',
                'label'     => __('Type'),
                'required'  => true,
                'labelProp' => 'name',
                'valueProp' => 'id',
                'options'   => $shop->appointmentTypes()->orderBy('name')->get(['id', 'name'])->toArray(),
                'value'     => $appointment?->appointment_type_id,
            ],
            'starts_at'           => [
                'type'        => 'appointment_slot',
                'label'       => __('Date and time'),
                'required'    => true,
                'information' => __('In the shop\'s timezone (:timezone).', ['timezone' => $timezone]),
                'minuteStep'  => 30,
                'value'       => $appointment?->starts_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            ],
            'user_id'             => [
                'type'        => 'select',
                'label'       => __('Staff'),
                'information' => __('Who meets the visitor. Leave empty and anyone who arranges this type can take it.'),
                'labelProp'   => 'name',
                'valueProp'   => 'id',
                'searchable'  => true,
                'options'     => User::whereIn(
                    'id',
                    $shop->appointmentTypes()
                        ->join('appointment_type_user', 'appointment_type_user.appointment_type_id', '=', 'appointment_types.id')
                        ->select('appointment_type_user.user_id')
                )
                    ->orderBy('contact_name')
                    ->get(['id', 'contact_name', 'username'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()]),
                'value'       => $appointment?->user_id,
            ],
        ];
    }

    protected function appointmentVisitorFields(?Appointment $appointment = null): array
    {
        return [
            'contact_name'    => [
                'type'     => 'input',
                'label'    => __('Name'),
                'required' => true,
                'value'    => $appointment?->contact_name ?? '',
            ],
            'email'           => [
                'type'        => 'input',
                'label'       => __('Email'),
                'information' => __('When it matches a customer of this shop, the appointment is linked to them.'),
                'value'       => $appointment?->email ?? '',
            ],
            'phone'           => [
                'type'  => 'input',
                'label' => __('Phone'),
                'value' => $appointment?->phone ?? '',
            ],
            'number_visitors' => [
                'type'  => 'input_number',
                'label' => __('People coming'),
                'bind'  => ['min' => 1, 'max' => 100],
                'value' => $appointment?->number_visitors ?? 1,
            ],
            'notes'           => [
                'type'  => 'textarea',
                'label' => __('Notes'),
                'value' => $appointment?->notes ?? '',
            ],
        ];
    }

    protected function appointmentStateField(Appointment $appointment): array
    {
        return [
            'type'     => 'select',
            'label'    => __('Status'),
            'required' => true,
            'options'  => AppointmentStateEnum::valuesWithLabels(),
            'value'    => $appointment->state->value,
        ];
    }
}
