<?php

namespace App\Actions\Iris\Appointment;

use App\Actions\CRM\Appointment\StoreAppointment;
use App\Actions\CRM\Appointment\WithAppointmentPhoneRules;
use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\IrisAction;
use App\Enums\CRM\Appointment\AppointmentSourceEnum;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisAppointment extends IrisAction
{
    use WithAppointmentPhoneRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData, ?int $customerId = null): Appointment
    {
        return DB::transaction(function () use ($shop, $modelData, $customerId) {
            /** @var AppointmentType $appointmentType */
            $appointmentType = AppointmentType::where('id', $modelData['appointment_type_id'])->lockForUpdate()->firstOrFail();

            if (!GetAppointmentTypeAvailableSlots::make()->isAvailable($appointmentType, $modelData['date'], $modelData['time'])) {
                throw ValidationException::withMessages([
                    'time' => __('Sorry, this time has just been taken. Please choose another one.'),
                ]);
            }

            return StoreAppointment::make()->action($shop, array_filter([
                'appointment_type_id' => $appointmentType->id,
                'starts_at'           => $modelData['date'].' '.$modelData['time'],
                'contact_name'        => $modelData['contact_name'],
                'email'               => $modelData['email'],
                'phone'               => Arr::get($modelData, 'phone'),
                'number_visitors'     => Arr::get($modelData, 'number_visitors', 1),
                'notes'               => Arr::get($modelData, 'notes'),
                'marketing_opt_in'    => (bool) Arr::get($modelData, 'marketing_opt_in', false),
                'customer_id'         => $customerId,
                'source'              => AppointmentSourceEnum::WEBSITE->value,
                'state'               => AppointmentStateEnum::REQUESTED->value,
            ], fn ($value) => !is_null($value) && $value !== ''));
        });
    }

    public function rules(): array
    {
        return [
            'appointment_type_id' => [
                'required',
                'integer',
                Rule::exists('appointment_types', 'id')
                    ->where('shop_id', $this->shop->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'date'                => ['required', 'date_format:Y-m-d'],
            'time'                => ['required', 'date_format:H:i'],
            'contact_name'        => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255'],
            'phone'               => $this->appointmentPhoneRules((int) $this->get('appointment_type_id')),
            'number_visitors'     => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'marketing_opt_in'    => ['sometimes', 'boolean'],
            'website_url'         => ['prohibited'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            ...$this->appointmentPhoneMessages(),
            'website_url.prohibited' => __('We could not send your request. Please reload the page and try again.'),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Appointment
    {
        $this->initialisation($request);

        return $this->handle($this->shop, $this->validatedData, $this->signedInCustomerId());
    }

    public function jsonResponse(Appointment $appointment): array
    {
        $timezone = $appointment->shop->timezone?->name ?? 'UTC';

        return [
            'id'               => $appointment->id,
            'appointment_type' => $appointment->appointmentType->name,
            'starts_at'        => $appointment->starts_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            'ends_at'          => $appointment->ends_at->copy()->setTimezone($timezone)->format('H:i'),
            'location'         => $appointment->appointmentType->location,
        ];
    }
}
