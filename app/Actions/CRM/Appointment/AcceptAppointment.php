<?php

namespace App\Actions\CRM\Appointment;

use App\Actions\OrgAction;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AcceptAppointment extends OrgAction
{
    use WithAppointmentStateChange;

    private Appointment $appointment;

    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $this->ensureStateIn($appointment, [AppointmentStateEnum::REQUESTED]);

        return UpdateAppointment::make()->action($appointment, [
            'state'   => AppointmentStateEnum::ACCEPTED->value,
            'user_id' => $modelData['user_id'],
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('appointment_type_user', 'user_id')->where('appointment_type_id', $this->appointment->appointment_type_id),
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'user_id.required' => __('Choose who arranges this appointment.'),
            'user_id.exists'   => __('This person does not arrange this type of appointment. Add them under Appointments › Staff first.'),
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
