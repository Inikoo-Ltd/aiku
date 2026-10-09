<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class RescheduleAppointment extends OrgAction
{
    use WithAppointmentStateChange;

    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $this->ensureStateIn($appointment, AppointmentStateEnum::holdingSlot());

        return UpdateAppointment::make()->action($appointment, [
            'starts_at' => $modelData['date'].' '.$modelData['time'],
            'state'     => AppointmentStateEnum::ACCEPTED->value,
        ]);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $this->get('date').' '.$this->get('time'), $this->shop->timezone?->name ?? 'UTC');
        if ($startsAt->isPast()) {
            $validator->errors()->add('time', __('Choose a time that has not passed yet.'));
        }
    }

    public function asController(Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisationFromShop($appointment->shop, $request);

        return $this->handle($appointment, $this->validatedData);
    }

    public function action(Appointment $appointment, array $modelData): Appointment
    {
        $this->asAction = true;
        $this->initialisationFromShop($appointment->shop, $modelData);

        return $this->handle($appointment, $this->validatedData);
    }
}
