<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $username
 * @property string|null $contact_name
 * @property string|null $appointment_types
 * @property int $number_appointment_types
 */
class AppointmentStaffResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'username'                 => $this->username,
            'contact_name'             => $this->contact_name ?: $this->username,
            'appointment_types'        => $this->appointment_types,
            'number_appointment_types' => $this->number_appointment_types,
        ];
    }
}
