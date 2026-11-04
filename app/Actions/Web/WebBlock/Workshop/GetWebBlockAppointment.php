<?php

/*
 * Author: YudhistiraA
 * Created: Tue, 03 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026
 */

namespace App\Actions\Web\WebBlock\Workshop;

use App\Models\CRM\AppointmentType;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Gives the appointment block in the workshop the shop's live appointment types, so the editor
 * can show and order the real ones. They sit beside the layout, never inside it, so saving the
 * block does not store them.
 */
class GetWebBlockAppointment
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $webBlock['appointment_types'] = $webpage->shop
            ? $webpage->shop->appointmentTypes()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (AppointmentType $appointmentType) => [
                    'id'                 => $appointmentType->id,
                    'name'               => $appointmentType->name,
                    'description'        => $appointmentType->description,
                    'meeting_mode'       => $appointmentType->meeting_mode->value,
                    'meeting_mode_label' => $appointmentType->meeting_mode->label(),
                    'media'              => $appointmentType->media()?->value,
                    'location'           => $appointmentType->location,
                    'duration_minutes'   => $appointmentType->duration_minutes,
                ])
                ->values()
                ->all()
            : [];

        return $webBlock;
    }
}
