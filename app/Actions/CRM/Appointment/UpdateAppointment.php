<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateAppointment extends OrgAction
{
    use WithActionUpdate;
    use WithAppointmentRules;
    use WithAppointmentNotification;

    private Appointment $appointment;

    /**
     * @throws \Throwable
     */
    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $previousState    = $appointment->state;
        $previousStartsAt = $appointment->starts_at->copy();

        return DB::transaction(function () use ($appointment, $modelData, $previousState, $previousStartsAt) {
            $modelData = $this->prepareAppointmentData($appointment->shop, $modelData, $appointment);

            $appointment = $this->update($appointment, $modelData);

            $this->notifyVisitorAboutChange($appointment, $previousState, $previousStartsAt);

            return $appointment;
        });
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
            'state'        => ['sometimes', Rule::enum(AppointmentStateEnum::class)],
            'state_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return $this->appointmentPhoneMessages();
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
