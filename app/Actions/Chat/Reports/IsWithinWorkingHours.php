<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Reports;

use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Employee;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class IsWithinWorkingHours
{
    use AsAction;

    /**
     * Whether a moment falls inside working hours, in the shop's own timezone.
     *
     * The agent's contracted hours are the truth when HR holds them, because they carry
     * the days somebody does not work and their breaks. Without them the shop's opening
     * hours answer, and without those a plain weekday nine to five. A public holiday of
     * the shop's organisation is never working time.
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

        $fromShop = Arr::get($shop->opening_hours ?? [], 'data.'.$isoWeekday);
        if (is_array($fromShop) && Arr::has($fromShop, ['s', 'e'])) {
            return $fromShop;
        }

        // A guess until HR fills the real hours in: every contract that is filled in says
        // 08:00 to 16:00, Monday to Friday, so that is the least wrong default.
        return $isoWeekday <= 5 ? ['s' => '08:00', 'e' => '16:00', 'b' => []] : null;
    }

    private function isPublicHoliday(Shop $shop, Carbon $local): bool
    {
        return $shop->organisation->holidays()
            ->where('type', HolidayTypeEnum::PUBLIC)
            ->whereDate('from', '<=', $local->toDateString())
            ->whereDate('to', '>=', $local->toDateString())
            ->exists();
    }
}
