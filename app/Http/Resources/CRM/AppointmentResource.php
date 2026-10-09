<?php

namespace App\Http\Resources\CRM;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->resource;

        $timezone = $appointment->shop->timezone?->name ?? 'UTC';
        $startsAt = $appointment->starts_at->copy()->setTimezone($timezone);
        $endsAt   = $appointment->ends_at->copy()->setTimezone($timezone);
        $visitor  = $appointment->visitor;

        $routeParameters = [
            'organisation' => $appointment->organisation->slug,
            'shop'         => $appointment->shop->slug,
        ];

        return [
            'id'               => $appointment->id,
            'state'            => $appointment->state->value,
            'state_label'      => $appointment->state->label(),
            'state_icon'       => $appointment->state->icon(),
            'state_reason'     => $appointment->state_reason,
            'date'             => $startsAt->toDateString(),
            'time'             => $startsAt->format('H:i'),
            'ends_time'        => $endsAt->format('H:i'),
            'date_label'       => $startsAt->translatedFormat('l j F Y'),
            'is_past'          => $appointment->ends_at->isPast(),
            'timezone'         => $timezone,
            'appointment_type' => [
                'name'               => $appointment->appointmentType?->name,
                'meeting_mode'       => $appointment->appointmentType?->meeting_mode->value,
                'meeting_mode_label' => $appointment->appointmentType?->meeting_mode->label(),
                'location'           => $appointment->appointmentType?->location,
                'duration_minutes'   => $appointment->appointmentType?->duration_minutes,
            ],
            'staff_name'       => $appointment->user?->chatName(),
            'contact_name'     => $appointment->contact_name,
            'email'            => $appointment->email,
            'phone'            => $appointment->phone,
            'whatsapp_url'     => $appointment->appointmentType?->requiresPhone() ? $appointment->whatsappUrl() : null,
            'number_visitors'  => $appointment->number_visitors,
            'notes'            => $appointment->notes,
            'visitor'          => match (true) {
                $visitor instanceof Customer => [
                    'type'  => 'customer',
                    'label' => __('Customer'),
                    'name'  => $visitor->name,
                    'route' => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => [...$routeParameters, 'customer' => $visitor->slug]],
                ],
                $visitor instanceof Prospect => [
                    'type'  => 'prospect',
                    'label' => __('Prospect'),
                    'name'  => $visitor->name ?: $visitor->contact_name,
                    'route' => ['name' => 'grp.org.shops.show.crm.prospects.show', 'parameters' => [...$routeParameters, 'prospect' => $visitor->slug]],
                ],
                default => null,
            },
            'source_label'     => $appointment->source->label(),
            'created_by'       => $appointment->createdBy?->chatName(),
            'created_at'       => $appointment->created_at,
            'accepted_at'      => $appointment->accepted_at,
            'declined_at'      => $appointment->declined_at,
            'cancelled_at'     => $appointment->cancelled_at,
            'can_accept'       => $appointment->state === AppointmentStateEnum::REQUESTED,
            'can_decline'      => $appointment->state->holdsSlot(),
            'can_reschedule'   => $appointment->state->holdsSlot(),
        ];
    }
}
