<?php

namespace App\Http\Resources\CRM;

use App\Models\CRM\Appointment;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->resource;

        $timezone = $appointment->shop->timezone?->name ?? 'UTC';
        $startsAt = $appointment->starts_at->copy()->setTimezone($timezone);
        $endsAt   = $appointment->ends_at->copy()->setTimezone($timezone);

        return [
            'id'                    => $appointment->id,
            'when'                  => $startsAt->translatedFormat('D j M Y, H:i').'–'.$endsAt->format('H:i'),
            'starts_at'             => $appointment->starts_at,
            'appointment_type_name' => $appointment->appointmentType?->name,
            'contact_name'          => $appointment->contact_name,
            'email'                 => $appointment->email,
            'phone'                 => $appointment->phone,
            'visitor_type'          => $appointment->visitor_type,
            'visitor_slug'          => $appointment->visitor?->slug,
            'number_visitors'       => $appointment->number_visitors,
            'staff_name'            => $appointment->user?->chatName(),
            'state'                 => $appointment->state->value,
            'state_icon'            => $appointment->state->icon(),
        ];
    }
}
