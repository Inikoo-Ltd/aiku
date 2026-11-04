<?php

namespace App\Actions\Retina\CRM\Appointment;

use App\Actions\CRM\Appointment\UpdateAppointment;
use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\RetinaAction;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RescheduleRetinaAppointment extends RetinaAction
{
    use WithRetinaAppointment;

    /**
     * @throws \Throwable
     */
    public function handle(Appointment $appointment, array $modelData): Appointment
    {
        $this->ensureCustomerCanChange($appointment);

        return $this->moveToFreeTime($appointment, $modelData['date'], $modelData['time']);
    }

    /**
     * Whoever booked picks from the same free times a new booking would get, and the appointment
     * keeps its state: an accepted one stays accepted with the member of staff it already has.
     *
     * @throws \Throwable
     */
    public function moveToFreeTime(Appointment $appointment, string $date, string $time): Appointment
    {
        $timeTaken = ValidationException::withMessages([
            'time' => __('Sorry, this time has just been taken. Please choose another one.'),
        ]);

        return DB::transaction(function () use ($appointment, $date, $time, $timeTaken) {
            GetAppointmentTypeAvailableSlots::make()->lockShopAppointmentTypes($appointment->shop);
            $appointmentType = AppointmentType::where('id', $appointment->appointment_type_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$appointmentType || !GetAppointmentTypeAvailableSlots::make()->isAvailable($appointmentType, $date, $time)) {
                throw $timeTaken;
            }

            try {
                return UpdateAppointment::make()->action($appointment, [
                    'starts_at' => $date.' '.$time,
                ]);
            } catch (ValidationException) {
                throw $timeTaken;
            }
        });
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Appointment $appointment, ActionRequest $request): Appointment
    {
        $this->initialisation($request);
        $this->ensureAppointmentOfCustomer($appointment, $this->customer);

        return $this->handle($appointment, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
