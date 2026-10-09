<?php

namespace App\Actions\CRM\Appointment;

use App\Models\CRM\AppointmentType;
use App\Rules\Phone;

trait WithAppointmentPhoneRules
{
    protected function appointmentPhoneRules(?int $appointmentTypeId, bool $isUpdate = false): array
    {
        $requiresPhone = $appointmentTypeId && AppointmentType::withTrashed()->find($appointmentTypeId)?->requiresPhone();

        if (!$requiresPhone) {
            return [...($isUpdate ? ['sometimes'] : []), 'nullable', 'string', 'max:64'];
        }

        return [...($isUpdate ? ['sometimes'] : []), 'required', 'string', 'max:64', 'regex:/^\s*(\+|00)/', new Phone()];
    }

    protected function appointmentPhoneMessages(): array
    {
        return [
            'phone.required' => __('We call video appointments on WhatsApp, so we need your phone number.'),
            'phone.regex'    => __('Start the number with your country code, for example +44.'),
        ];
    }
}
