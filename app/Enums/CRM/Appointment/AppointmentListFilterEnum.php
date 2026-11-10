<?php

namespace App\Enums\CRM\Appointment;

use App\Enums\EnumHelperTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

enum AppointmentListFilterEnum: string
{
    use EnumHelperTrait;

    case REQUESTED = 'requested';
    case UPCOMING  = 'upcoming';
    case PAST      = 'past';
    case DECLINED  = 'declined';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => __('Requested'),
            self::UPCOMING  => __('Upcoming'),
            self::PAST      => __('Past'),
            self::DECLINED  => __('Declined'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function constrain(Builder|QueryBuilder $query, Carbon $now): void
    {
        match ($this) {
            self::REQUESTED => $query
                ->where('appointments.state', AppointmentStateEnum::REQUESTED)
                ->where('appointments.ends_at', '>=', $now),
            self::UPCOMING  => $query
                ->where('appointments.state', AppointmentStateEnum::ACCEPTED)
                ->where('appointments.ends_at', '>=', $now),
            self::PAST      => $query->where(fn ($query) => $query
                ->whereIn('appointments.state', AppointmentStateEnum::finished())
                ->orWhere(fn ($query) => $query
                    ->whereIn('appointments.state', AppointmentStateEnum::holdingSlot())
                    ->where('appointments.ends_at', '<', $now))),
            self::DECLINED  => $query->where('appointments.state', AppointmentStateEnum::DECLINED),
            self::CANCELLED => $query->where('appointments.state', AppointmentStateEnum::CANCELLED),
        };
    }
}
