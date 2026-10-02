<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Models\Catalogue\Shop;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Web\Website;

class GetChatConfig
{
    use AsAction;

    public function handle(Website $website): array
    {
        return $this->forShop($website->shop, (bool) ($website->settings['enable_chat'] ?? false));
    }

    /**
     * A shop running our widget on a storefront that is not ours has no website of ours to read
     * the switch from, so its own chat setting stands in for it. Everything after that was always
     * the shop's: the schedule, the timezone and the working hours.
     */
    public function forShop(?Shop $shop, bool $chatEnabled): array
    {
        $config = [
            'is_online'     => false,
            'schedule'      => null,
            'offline_info'  => null,
        ];

        if (!$chatEnabled || !$shop) {
            return $config;
        }


        $effective = $shop->getEffectiveWorkSchedule();
        $schedule  = $effective['schedule'];
        $timezone  = $effective['timezone'];

        if (!$schedule) {
            $config['is_online'] = false;

            return $config;
        }

        $config['is_online'] = IsWithinWorkingHours::run($shop, now());

        $now = Carbon::now($timezone);
        $dayOfWeek = $now->dayOfWeekIso;
        $days = collect($schedule->days ?? []);
        $todaySchedule = $days->firstWhere('day_of_week', $dayOfWeek);

        if ($todaySchedule && $todaySchedule->is_working_day) {
            $config['schedule'] = $this->formatScheduleWindow(
                (string) $todaySchedule->start_time,
                (string) $todaySchedule->end_time,
                $timezone
            );
        }

        if (!$config['is_online']) {
            $config['offline_info'] = $this->buildOfflineInfo(
                $shop,
                $todaySchedule,
                $dayOfWeek,
                $timezone
            );
        }

        return $config;
    }

    private function buildOfflineInfo(
        Shop $shop,
        mixed $todaySchedule,
        int $currentDayOfWeek,
        string $timezone
    ): array {
        $isTodayWorkingDay = (bool) ($todaySchedule?->is_working_day ?? false);
        $reason = $isTodayWorkingDay ? 'outside_working_hours' : 'non_working_day';
        $nextOpening = IsWithinWorkingHours::make()->nextOpening($shop, now());

        return [
            'reason' => $reason,
            'today'  => [
                'day_of_week' => $currentDayOfWeek,
                'day_name'    => $this->dayNameFromIso($currentDayOfWeek),
                'is_working_day' => $isTodayWorkingDay,
            ],
            'next_opening' => $nextOpening
                ? [
                    'day_of_week' => $nextOpening['opens']->isoWeekday(),
                    'day_name'    => $this->dayNameFromIso($nextOpening['opens']->isoWeekday()),
                    'start'       => $nextOpening['opens']->format('H:i:s'),
                    'end'         => $nextOpening['closes']->format('H:i:s'),
                    'timezone'    => $timezone,
                ]
                : null,
        ];
    }

    private function dayNameFromIso(int $dayOfWeekIso): string
    {
        return match ($dayOfWeekIso) {
            1 => __('Monday'),
            2 => __('Tuesday'),
            3 => __('Wednesday'),
            4 => __('Thursday'),
            5 => __('Friday'),
            6 => __('Saturday'),
            7 => __('Sunday'),
            default => __('Unknown'),
        };
    }

    private function formatScheduleWindow(string $startTime, string $endTime, string $timezone): array
    {
        return [
            'start'    => $this->formatTime($startTime),
            'end'      => $this->formatTime($endTime),
            'timezone' => $timezone,
        ];
    }

    private function formatTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        return Carbon::parse($time)->format('H:i:s');
    }
}
