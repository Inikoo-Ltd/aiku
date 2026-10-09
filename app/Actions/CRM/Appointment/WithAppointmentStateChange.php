<?php

namespace App\Actions\CRM\Appointment;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

trait WithAppointmentStateChange
{
    /**
     * @param array<int, AppointmentStateEnum> $allowedStates
     */
    protected function ensureStateIn(Appointment $appointment, array $allowedStates): void
    {
        if (!in_array($appointment->state, $allowedStates, true)) {
            throw ValidationException::withMessages([
                'state' => __('This appointment is :state and can no longer be changed this way.', ['state' => strtolower($appointment->state->label())]),
            ]);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
