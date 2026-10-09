<?php

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;

enum AppointmentSourceEnum: string
{
    use EnumHelperTrait;

    case STAFF   = 'staff';
    case WEBSITE = 'website';

    public function label(): string
    {
        return match ($this) {
            self::STAFF   => __('Booked by staff'),
            self::WEBSITE => __('Booked on the website'),
        };
    }
}
