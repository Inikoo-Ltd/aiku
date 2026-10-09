<?php

namespace App\Actions\CRM\AppointmentType;

use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentType;
use App\Models\CRM\AppointmentTypeDate;
use Illuminate\Support\Arr;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAppointmentTypeAvailableSlots
{
    use AsObject;

    /**
     * @return array<string, array<int, string>> free start times (H:i, shop timezone) keyed by date (Y-m-d)
     */
    public function handle(AppointmentType $appointmentType, ?CarbonInterface $now = null): array
    {
        $timezone = $appointmentType->shop->timezone?->name ?? 'UTC';
        $now      = Carbon::instance($now ?? now())->setTimezone($timezone);
        $earliest = $now->copy()->addHours($appointmentType->min_notice_hours);
        $lastDay  = $now->copy()->startOfDay()->addDays($appointmentType->booking_window_days);

        $dateHours = $appointmentType->dates()
            ->whereBetween('date', [$now->toDateString(), $lastDay->toDateString()])
            ->get()
            ->mapWithKeys(fn (AppointmentTypeDate $date) => [$date->date->toDateString() => $date->hours]);

        $buffer   = $appointmentType->buffer_minutes;
        $duration = $appointmentType->duration_minutes;
        $booked   = $this->bookedPeriods($appointmentType, $now, $lastDay->copy()->endOfDay(), $buffer);

        $slots = [];
        for ($day = $now->copy()->startOfDay(); $day->lte($lastDay); $day->addDay()) {
            $date   = $day->toDateString();
            $ranges = $dateHours->has($date) ? $dateHours->get($date) : Arr::get($appointmentType->weekly_hours, $day->isoWeekday(), []);

            $times = [];
            foreach ($ranges as $range) {
                $start = Carbon::parse($date.' '.$range['from'], $timezone);
                $close = Carbon::parse($date.' '.$range['to'], $timezone);

                for ($slot = $start; $slot->copy()->addMinutes($duration)->lte($close); $slot = $slot->copy()->addMinutes($duration + $buffer)) {
                    if ($slot->lt($earliest)) {
                        continue;
                    }
                    if ($this->countOverlapping($booked, $slot, $slot->copy()->addMinutes($duration + $buffer)) < $appointmentType->capacity_per_slot) {
                        $times[] = $slot->format('H:i');
                    }
                }
            }

            if ($times) {
                $slots[$date] = $times;
            }
        }

        return $slots;
    }

    public function isAvailable(AppointmentType $appointmentType, string $date, string $time, ?CarbonInterface $now = null): bool
    {
        return in_array($time, $this->handle($appointmentType, $now)[$date] ?? [], true);
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function bookedPeriods(AppointmentType $appointmentType, Carbon $from, Carbon $to, int $buffer): Collection
    {
        return Appointment::where('appointment_type_id', $appointmentType->id)
            ->where('state', AppointmentStateEnum::BOOKED)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from->copy()->subMinutes($buffer))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [$appointment->starts_at, $appointment->ends_at->copy()->addMinutes($buffer)]);
    }

    private function countOverlapping(Collection $booked, Carbon $slotStart, Carbon $slotEnd): int
    {
        return $booked->filter(fn (array $period) => $period[0]->lt($slotEnd) && $period[1]->gt($slotStart))->count();
    }
}
