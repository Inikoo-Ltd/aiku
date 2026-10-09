<?php

namespace App\Actions\CRM\Appointment;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

trait WithAppointmentRules
{
    protected function appointmentRules(bool $isUpdate, ?Appointment $appointment = null): array
    {
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];
        $optional = $isUpdate ? ['sometimes'] : ['nullable'];

        $appointmentTypeId = $this->get('appointment_type_id', $appointment?->appointment_type_id);

        return [
            'appointment_type_id' => [
                ...$required,
                'integer',
                Rule::exists('appointment_types', 'id')->where('shop_id', $this->shop->id)->whereNull('deleted_at'),
            ],
            'starts_at'           => [...$required, 'date_format:Y-m-d H:i'],
            'contact_name'        => [...$required, 'string', 'max:255'],
            'email'               => [...$optional, 'nullable', 'email', 'max:255'],
            'phone'               => [...$optional, 'nullable', 'string', 'max:64'],
            'number_visitors'     => [...$optional, 'integer', 'min:1', 'max:100'],
            'notes'               => [...$optional, 'nullable', 'string', 'max:5000'],
            'user_id'             => [
                ...$optional,
                'nullable',
                'integer',
                Rule::exists('appointment_type_user', 'user_id')->where('appointment_type_id', (int) $appointmentTypeId),
            ],
        ];
    }

    protected function prepareAppointmentData(Shop $shop, array $modelData, ?Appointment $appointment = null): array
    {
        if (Arr::has($modelData, 'starts_at')) {
            $modelData['starts_at'] = Carbon::createFromFormat('Y-m-d H:i', $modelData['starts_at'], $shop->timezone?->name ?? 'UTC')->utc();
        }

        if (Arr::hasAny($modelData, ['starts_at', 'appointment_type_id'])) {
            $appointmentType      = AppointmentType::withTrashed()->find($modelData['appointment_type_id'] ?? $appointment->appointment_type_id);
            $startsAt             = $modelData['starts_at'] ?? $appointment->starts_at;
            $modelData['ends_at'] = $startsAt->copy()->addMinutes($appointmentType->duration_minutes);
        }

        if (Arr::has($modelData, 'email')) {
            $modelData['customer_id'] = $modelData['email']
                ? Customer::where('shop_id', $shop->id)->whereRaw('lower(email) = ?', [strtolower($modelData['email'])])->value('id')
                : null;
        }

        if (Arr::has($modelData, 'state')) {
            $state                     = $modelData['state'] instanceof AppointmentStateEnum ? $modelData['state'] : AppointmentStateEnum::from($modelData['state']);
            $modelData['cancelled_at'] = $state === AppointmentStateEnum::CANCELLED ? now() : null;
        }

        return $modelData;
    }
}
