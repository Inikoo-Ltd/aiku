<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Reports;

use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\WorkScheduleDay;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use WeakMap;

class IsWithinWorkingHours
{
    use AsAction;

    /**
     * The chat reports ask this once per conversation for the same shop, so each shop's week is
     * read once. Keyed by the model object, so it goes when the request lets go of the shop.
     *
     * @var WeakMap<Shop, Collection<int, WorkScheduleDay>>|null
     */
    private static ?WeakMap $shopWeeks = null;

    /**
     * Whether a moment falls inside working hours, in the shop's own timezone.
     *
     * The agent's contracted hours are the truth when HR holds them, because they carry
     * the days somebody does not work and their breaks. Without them the shop's work schedule
     * answers (its own, else its organisation's: the same hours the chat widget shows), and
     * without one a plain weekday eight to four. A public holiday of the shop's organisation
     * is never working time.
     */
    public function handle(Shop $shop, Carbon $at, ?Employee $employee = null): bool
    {
        $local = $at->copy()->setTimezone($shop->timezoneName());

        if ($this->isPublicHoliday($shop, $local)) {
            return false;
        }

        $day = $this->dayHours($employee, $shop, (int) $local->isoWeekday());

        if (!$day) {
            return false;
        }

        $time = $local->format('H:i');

        if ($time < Arr::get($day, 's') || $time >= Arr::get($day, 'e')) {
            return false;
        }

        foreach (Arr::get($day, 'b', []) ?? [] as $break) {
            if ($time >= Arr::get($break, 's') && $time < Arr::get($break, 'e')) {
                return false;
            }
        }

        return true;
    }

    /**
     * When the shop is next open after a moment, skipping public holidays: later today when it
     * has not opened yet, else the first working day after. Null when nothing opens in the
     * next five weeks, which only a schedule with no working day at all can cause.
     *
     * @return array{opens: Carbon, closes: Carbon}|null
     */
    public function nextOpening(Shop $shop, Carbon $at): ?array
    {
        $local = $at->copy()->setTimezone($shop->timezoneName());

        foreach (range(0, 35) as $offset) {
            $date = $local->copy()->startOfDay()->addDays($offset);
            $day  = $this->dayHours(null, $shop, (int) $date->isoWeekday());

            if (!$day || $this->isPublicHoliday($shop, $date)) {
                continue;
            }

            $opens = $date->copy()->setTimeFromTimeString(Arr::get($day, 's'));

            if ($opens->gt($local)) {
                return ['opens' => $opens, 'closes' => $date->copy()->setTimeFromTimeString(Arr::get($day, 'e'))];
            }
        }

        return null;
    }

    /**
     * @return array{s: string, e: string, b?: array<int, array{s: string, e: string}>}|null
     */
    private function dayHours(?Employee $employee, Shop $shop, int $isoWeekday): ?array
    {
        $contract = Arr::get($employee?->working_hours ?? [], 'data');

        if (is_array($contract) && $contract !== []) {
            // A contract answers for every day of its week: a day it does not list is a
            // day this person does not work, which is the whole point of reading it.
            $day = Arr::get($contract, (string) $isoWeekday);

            return is_array($day) && Arr::has($day, ['s', 'e']) ? $day : null;
        }

        $week = $this->shopWeek($shop);

        if ($week->isNotEmpty()) {
            $day = $week->first(fn (WorkScheduleDay $day) => $day->day_of_week === $isoWeekday && $day->is_working_day);

            return $day ? ['s' => substr((string) $day->start_time, 0, 5), 'e' => substr((string) $day->end_time, 0, 5), 'b' => []] : null;
        }

        // A guess until a schedule exists: every contract that is filled in says
        // 08:00 to 16:00, Monday to Friday, so that is the least wrong default.
        return $isoWeekday <= 5 ? ['s' => '08:00', 'e' => '16:00', 'b' => []] : null;
    }

    /**
     * @return Collection<int, WorkScheduleDay>
     */
    private function shopWeek(Shop $shop): Collection
    {
        self::$shopWeeks ??= new WeakMap();

        return self::$shopWeeks[$shop] ??= $shop->getEffectiveWorkSchedule()['schedule']?->days()->get() ?? collect();
    }

    private function isPublicHoliday(Shop $shop, Carbon $local): bool
    {
        return $this->publicHoliday($shop, $local) !== null;
    }

    public function publicHoliday(Shop $shop, Carbon $local): ?Holiday
    {
        return $shop->organisation->holidays()
            ->where('type', HolidayTypeEnum::PUBLIC)
            ->whereDate('from', '<=', $local->toDateString())
            ->whereDate('to', '>=', $local->toDateString())
            ->first();
    }
}
