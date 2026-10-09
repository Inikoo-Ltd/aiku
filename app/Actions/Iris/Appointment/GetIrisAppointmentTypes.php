<?php

namespace App\Actions\Iris\Appointment;

use App\Actions\CRM\AppointmentType\GetAppointmentTypeAvailableSlots;
use App\Actions\IrisAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use Lorisleiva\Actions\ActionRequest;

class GetIrisAppointmentTypes extends IrisAction
{
    /**
     * @return array{shop_name: string, timezone: string, appointment_types: array<int, array<string, mixed>>}
     */
    public function handle(Shop $shop): array
    {
        return [
            'shop_name'         => $shop->name,
            'timezone'          => $shop->timezone?->name ?? 'UTC',
            'appointment_types' => $shop->appointmentTypes()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (AppointmentType $appointmentType) => [
                    'id'                => $appointmentType->id,
                    'name'              => $appointmentType->name,
                    'description'       => $appointmentType->description,
                    'meeting_mode'      => $appointmentType->meeting_mode->value,
                    'meeting_mode_label' => $appointmentType->meeting_mode->label(),
                    'location'          => $appointmentType->location,
                    'duration_minutes'  => $appointmentType->duration_minutes,
                    'slots'             => (object) GetAppointmentTypeAvailableSlots::run($appointmentType),
                ])
                ->values()
                ->all(),
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->shop);
    }
}
