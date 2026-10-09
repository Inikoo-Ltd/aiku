<?php

namespace App\Actions\CRM\AppointmentType\UI;

use App\Enums\CRM\AppointmentType\AppointmentTypeMeetingModeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentType;
use App\Models\CRM\AppointmentTypeDate;

trait WithAppointmentTypeForm
{
    protected function appointmentTypeFormBlueprint(Shop $shop, ?AppointmentType $appointmentType = null): array
    {
        return [
            [
                'title'  => __('Details'),
                'label'  => __('Details'),
                'icon'   => 'fal fa-info-circle',
                'fields' => [
                    'name'         => [
                        'type'        => 'input',
                        'label'       => __('Name'),
                        'placeholder' => __('Showroom visit'),
                        'required'    => true,
                        'value'       => $appointmentType?->name ?? '',
                    ],
                    'meeting_mode' => [
                        'type'     => 'select',
                        'label'    => __('Visit type'),
                        'required' => true,
                        'options'  => AppointmentTypeMeetingModeEnum::valuesWithLabels(),
                        'value'    => $appointmentType?->meeting_mode->value ?? AppointmentTypeMeetingModeEnum::STORE_VISIT->value,
                    ],
                    'location'     => [
                        'type'        => 'input',
                        'label'       => __('Location'),
                        'information' => __('Where the customer comes to, or the meeting link for a video call.'),
                        'value'       => $appointmentType?->location ?? '',
                    ],
                    'description'  => [
                        'type'        => 'textarea',
                        'label'       => __('Description'),
                        'information' => __('Shown to the customer when they book.'),
                        'value'       => $appointmentType?->description ?? '',
                    ],
                    'is_active'    => [
                        'type'        => 'toggle',
                        'label'       => __('Open for booking'),
                        'information' => __('When off, customers cannot book this appointment.'),
                        'value'       => $appointmentType?->is_active ?? true,
                    ],
                ],
            ],
            [
                'title'  => __('Opening hours'),
                'label'  => __('Opening hours'),
                'icon'   => 'fal fa-clock',
                'fields' => [
                    'availability' => [
                        'type'        => 'appointment_availability',
                        'label'       => __('Opening hours'),
                        'information' => __('Times are in the shop\'s timezone (:timezone).', ['timezone' => $shop->timezone?->name ?? 'UTC']),
                        'full'        => true,
                        'minuteStep'  => 30,
                        'value'       => $this->availabilityFieldValue($shop, $appointmentType),
                    ],
                ],
            ],
            [
                'title'  => __('Booking rules'),
                'label'  => __('Booking rules'),
                'icon'   => 'fal fa-sliders-h',
                'fields' => [
                    'duration_minutes'    => [
                        'type'     => 'input_number',
                        'label'    => __('Duration'),
                        'required' => true,
                        'bind'     => ['suffix' => ' '.__('min'), 'min' => 5, 'max' => 480],
                        'value'    => $appointmentType?->duration_minutes ?? 45,
                    ],
                    'buffer_minutes'      => [
                        'type'        => 'input_number',
                        'label'       => __('Break between appointments'),
                        'information' => __('Time kept free after each appointment.'),
                        'bind'        => ['suffix' => ' '.__('min'), 'min' => 0, 'max' => 240],
                        'value'       => $appointmentType?->buffer_minutes ?? 0,
                    ],
                    'capacity_per_slot'   => [
                        'type'        => 'input_number',
                        'label'       => __('Bookings per time slot'),
                        'information' => __('How many separate bookings can share the same time.'),
                        'bind'        => ['min' => 1, 'max' => 100],
                        'value'       => $appointmentType?->capacity_per_slot ?? 1,
                    ],
                    'min_notice_hours'    => [
                        'type'        => 'input_number',
                        'label'       => __('Minimum notice'),
                        'information' => __('How long before the appointment it can still be booked.'),
                        'bind'        => ['suffix' => ' '.__('hours'), 'min' => 0, 'max' => 720],
                        'value'       => $appointmentType?->min_notice_hours ?? 24,
                    ],
                    'booking_window_days' => [
                        'type'        => 'input_number',
                        'label'       => __('Bookable ahead'),
                        'information' => __('How far into the future customers can book.'),
                        'bind'        => ['suffix' => ' '.__('days'), 'min' => 1, 'max' => 365],
                        'value'       => $appointmentType?->booking_window_days ?? 60,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{weekly: array<int, array<int, array{from: string, to: string}>>, dates: array<int, array{date: string, hours: array}>}
     */
    protected function availabilityFieldValue(Shop $shop, ?AppointmentType $appointmentType): array
    {
        $weekly = [];
        foreach (range(1, 7) as $dayOfWeek) {
            $weekly[$dayOfWeek] = $appointmentType?->weekly_hours[$dayOfWeek] ?? [];
        }

        $dates = [];
        if ($appointmentType) {
            $dates = $appointmentType->dates()
                ->where('date', '>=', now($shop->timezone?->name)->toDateString())
                ->orderBy('date')
                ->get()
                ->map(fn (AppointmentTypeDate $date) => [
                    'date'  => $date->date->toDateString(),
                    'hours' => $date->hours,
                ])
                ->all();
        }

        return [
            'weekly' => $weekly,
            'dates'  => $dates,
        ];
    }
}
