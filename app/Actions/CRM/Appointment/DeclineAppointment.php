<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class DeclineAppointment extends OrgAction
{
    use WithAppointmentStateChange;

    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $this->ensureStateIn($appointment, AppointmentStateEnum::holdingSlot());

        return UpdateAppointment::make()->action($appointment, [
            'state'        => AppointmentStateEnum::DECLINED->value,
            'state_reason' => Arr::get($modelData, 'reason'),
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function asController(Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisationFromShop($appointment->shop, $request);

        return $this->handle($appointment, $this->validatedData);
    }

    public function action(Appointment $appointment, array $modelData = []): Appointment
    {
        $this->asAction = true;
        $this->initialisationFromShop($appointment->shop, $modelData);

        return $this->handle($appointment, $this->validatedData);
    }
}
