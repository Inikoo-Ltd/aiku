<?php

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;

enum AppointmentStateEnum: string
{
    use EnumHelperTrait;

    case BOOKED    = 'booked';
    case COMPLETED = 'completed';
    case NO_SHOW   = 'no_show';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::BOOKED    => __('Booked'),
            self::COMPLETED => __('Completed'),
            self::NO_SHOW   => __('No show'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function icon(): array
    {
        return [
            'tooltip' => $this->label(),
            'icon'    => match ($this) {
                self::BOOKED    => 'fal fa-calendar-check',
                self::COMPLETED => 'fal fa-check-circle',
                self::NO_SHOW   => 'fal fa-user-slash',
                self::CANCELLED => 'fal fa-times-circle',
            },
            'class'   => match ($this) {
                self::BOOKED    => 'text-amber-500',
                self::COMPLETED => 'text-green-500',
                self::NO_SHOW   => 'text-gray-400',
                self::CANCELLED => 'text-red-500',
            },
        ];
    }
}
