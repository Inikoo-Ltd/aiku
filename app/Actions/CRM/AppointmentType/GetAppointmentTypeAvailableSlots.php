<?php

namespace App\Actions\CRM\AppointmentType;

use App\Actions\CRM\Appointment\GoogleCalendar\GetGoogleCalendarBusyPeriods;
use App\Enums\CRM\Appointment\AppointmentStateEnum;
use App\Models\CRM\Appointment;
use App\Models\CRM\AppointmentBlock;
use App\Models\CRM\AppointmentType;
use App\Models\CRM\AppointmentTypeDate;
use App\Models\HumanResources\CalendarEvent;
use App\Models\SysAdmin\User;
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
        $staffIds = $appointmentType->attendees()->where('users.status', true)->pluck('users.id');
        $busy     = $this->staffBusyElsewhere($appointmentType, $staffIds, $now, $lastDay->copy()->endOfDay());
        $calendar = GetGoogleCalendarBusyPeriods::run($appointmentType->shop, $now->copy()->startOfHour(), $lastDay->copy()->endOfDay())
            ->concat($this->blockedPeriods($appointmentType, $now, $lastDay->copy()->endOfDay()));

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
                    if ($this->countOverlapping($booked, $slot, $slot->copy()->addMinutes($duration + $buffer)) >= $appointmentType->capacity_per_slot) {
                        continue;
                    }
                    if ($this->countOverlapping($calendar, $slot, $slot->copy()->addMinutes($duration)) > 0) {
                        continue;
                    }
                    if ($this->hasStaffFree($staffIds, $busy, $slot, $slot->copy()->addMinutes($duration))) {
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
     * The member of staff a booking that arrives on its own is given: someone who arranges this
     * type and is not at an appointment of another type then, the least booked that day first.
     */
    public function freeStaffId(AppointmentType $appointmentType, Carbon $startsAt, Carbon $endsAt): ?int
    {
        $staffIds     = $appointmentType->attendees()->where('users.status', true)->pluck('users.id');
        $busyStaffIds = $this->staffBusyElsewhere($appointmentType, $staffIds, $startsAt->copy()->utc(), $endsAt->copy()->utc())->pluck(2);
        $freeStaffIds = $staffIds->diff($busyStaffIds)->values();

        if ($freeStaffIds->isEmpty()) {
            return null;
        }

        $bookingsThatDay = Appointment::whereIn('user_id', $freeStaffIds)
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->whereBetween('starts_at', [$startsAt->copy()->startOfDay()->utc(), $startsAt->copy()->endOfDay()->utc()])
            ->selectRaw('user_id, count(*) as bookings')
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        return $freeStaffIds
            ->sortBy([
                fn (int $a, int $b) => ($bookingsThatDay[$a] ?? 0) <=> ($bookingsThatDay[$b] ?? 0),
                fn (int $a, int $b) => $a <=> $b,
            ])
            ->first();
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function bookedPeriods(AppointmentType $appointmentType, Carbon $from, Carbon $to, int $buffer): Collection
    {
        return Appointment::where('appointment_type_id', $appointmentType->id)
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from->copy()->subMinutes($buffer))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $appointment) => [$appointment->starts_at, $appointment->ends_at->copy()->addMinutes($buffer)]);
    }

    /**
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function blockedPeriods(AppointmentType $appointmentType, Carbon $from, Carbon $to): Collection
    {
        return AppointmentBlock::where('shop_id', $appointmentType->shop_id)
            ->affecting($appointmentType->id)
            ->overlapping($from, $to)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (AppointmentBlock $block) => [$block->starts_at, $block->ends_at]);
    }

    /**
     * @param Collection<int, int> $staffIds
     *
     * @return Collection<int, array{0: Carbon, 1: Carbon, 2: int}> appointments of other types and calendar events that keep one of this type's staff away
     */
    private function staffBusyElsewhere(AppointmentType $appointmentType, Collection $staffIds, Carbon $from, Carbon $to): Collection
    {
        if ($staffIds->isEmpty()) {
            return collect();
        }

        $appointments = Appointment::whereIn('user_id', $staffIds)
            ->where('appointment_type_id', '!=', $appointmentType->id)
            ->whereIn('state', AppointmentStateEnum::holdingSlot())
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at', 'user_id'])
            ->map(fn (Appointment $appointment) => [$appointment->starts_at, $appointment->ends_at, $appointment->user_id]);

        $calendarEvents = CalendarEvent::overlapping($from, $to)
            ->whereHas('participants', fn ($query) => $query->whereIn('users.id', $staffIds))
            ->with(['participants' => fn ($query) => $query->whereIn('users.id', $staffIds)->select('users.id')])
            ->get(['id', 'starts_at', 'ends_at'])
            ->flatMap(fn (CalendarEvent $event) => $event->participants->map(fn (User $participant) => [$event->starts_at, $event->ends_at, $participant->id]));

        return $appointments->concat($calendarEvents)->values();
    }

    /**
     * A type nobody arranges yet is not held back by staff.
     *
     * @param Collection<int, int> $staffIds
     */
    private function hasStaffFree(Collection $staffIds, Collection $busy, Carbon $slotStart, Carbon $slotEnd): bool
    {
        if ($staffIds->isEmpty() || $busy->isEmpty()) {
            return true;
        }

        $busyStaffIds = $busy
            ->filter(fn (array $period) => $period[0]->lt($slotEnd) && $period[1]->gt($slotStart))
            ->pluck(2)
            ->unique();

        return $staffIds->diff($busyStaffIds)->isNotEmpty();
    }

    private function countOverlapping(Collection $booked, Carbon $slotStart, Carbon $slotEnd): int
    {
        return $booked->filter(fn (array $period) => $period[0]->lt($slotEnd) && $period[1]->gt($slotStart))->count();
    }
}
