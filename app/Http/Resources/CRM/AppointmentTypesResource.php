<?php

namespace App\Http\Resources\CRM;

use App\Models\CRM\AppointmentType;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int|null $attendees_count
 */
class AppointmentTypesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var AppointmentType $appointmentType */
        $appointmentType = $this->resource;

        return [
            'id'                => $appointmentType->id,
            'slug'              => $appointmentType->slug,
            'name'              => $appointmentType->name,
            'meeting_mode'      => $appointmentType->meeting_mode->label(),
            'meeting_mode_icon' => $appointmentType->meeting_mode->icon(),
            'location'          => $appointmentType->location,
            'duration_minutes'  => $appointmentType->duration_minutes,
            'capacity_per_slot' => $appointmentType->capacity_per_slot,
            'attendees_count'   => $this->attendees_count ?? 0,
            'is_active'         => $appointmentType->is_active,
            'is_active_icon'    => $appointmentType->is_active
                ? ['tooltip' => __('Open for booking'), 'icon' => 'fal fa-check', 'class' => 'text-green-500']
                : ['tooltip' => __('Closed for booking'), 'icon' => 'fal fa-times', 'class' => 'text-gray-400'],
        ];
    }
}
