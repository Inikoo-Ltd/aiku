<?php

namespace App\Actions\CRM\Appointment\Json;

use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\OrgAction;
use App\Models\CRM\Appointment;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class GetAppointmentActionOptions extends OrgAction
{
    /**
     * @return array{staff: array<int, array{id: int, name: string}>, free_slots: object, user_id: int|null}
     */
    public function handle(Appointment $appointment): array
    {
        $appointmentType = $appointment->appointmentType;

        return [
            'staff'      => $appointmentType
                ? $appointmentType->attendees()
                    ->where('users.status', true)
                    ->orderBy('contact_name')
                    ->get(['users.id', 'users.contact_name', 'users.username'])
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()])
                    ->values()
                    ->all()
                : [],
            'free_slots' => (object) ($appointmentType && $appointment->state->holdsSlot() ? GetAppointmentTypeAvailableSlots::run($appointmentType) : []),
            'user_id'    => $appointment->user_id,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "crm.{$this->shop->id}.view",
            "crm.{$this->shop->id}.edit",
        ]);
    }

    public function asController(Appointment $appointment, ActionRequest $request): array
    {
        $this->initialisationFromShop($appointment->shop, $request);

        return $this->handle($appointment);
    }
}
