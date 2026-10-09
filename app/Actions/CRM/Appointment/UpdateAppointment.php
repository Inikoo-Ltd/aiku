<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateAppointment extends OrgAction
{
    use WithActionUpdate;
    use WithAppointmentRules;

    private Appointment $appointment;

    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $modelData = $this->prepareAppointmentData($appointment->shop, $modelData, $appointment);

        return $this->update($appointment, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            ...$this->appointmentRules(isUpdate: true, appointment: $this->appointment),
            'state' => ['sometimes', Rule::enum(AppointmentStateEnum::class)],
        ];
    }

    public function asController(Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->appointment = $appointment;
        $this->initialisationFromShop($appointment->shop, $request);

        return $this->handle($appointment, $this->validatedData);
    }

    public function action(Appointment $appointment, array $modelData): Appointment
    {
        $this->asAction    = true;
        $this->appointment = $appointment;
        $this->initialisationFromShop($appointment->shop, $modelData);

        return $this->handle($appointment, $this->validatedData);
    }
}
