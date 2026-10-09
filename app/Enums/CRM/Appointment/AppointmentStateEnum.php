<?php

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;

enum AppointmentStateEnum: string
{
    use EnumHelperTrait;

    case REQUESTED = 'requested';
    case ACCEPTED  = 'accepted';
    case DECLINED  = 'declined';
    case COMPLETED = 'completed';
    case NO_SHOW   = 'no_show';
    case CANCELLED = 'cancelled';

    /**
     * @return array<int, self>
     */
    public static function holdingSlot(): array
    {
        return [self::REQUESTED, self::ACCEPTED];
    }

    /**
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [self::DECLINED, self::CANCELLED];
    }

    public function holdsSlot(): bool
    {
        return in_array($this, self::holdingSlot(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => __('Requested'),
            self::ACCEPTED  => __('Accepted'),
            self::DECLINED  => __('Declined'),
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
                self::REQUESTED => 'fal fa-hourglass-half',
                self::ACCEPTED  => 'fal fa-calendar-check',
                self::DECLINED  => 'fal fa-ban',
                self::COMPLETED => 'fal fa-check-circle',
                self::NO_SHOW   => 'fal fa-user-slash',
                self::CANCELLED => 'fal fa-times-circle',
            },
            'class'   => match ($this) {
                self::REQUESTED => 'text-amber-500',
                self::ACCEPTED, self::COMPLETED => 'text-green-500',
                self::DECLINED, self::CANCELLED => 'text-red-500',
                self::NO_SHOW   => 'text-gray-400',
            },
        ];
    }
}
