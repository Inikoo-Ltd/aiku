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

        $chatHours           = IsWithinWorkingHours::make()->chatHours();
        $config['is_online'] = $chatHours->handle($shop, now());

        $now = Carbon::now($timezone);
        $dayOfWeek = $now->dayOfWeekIso;
        $days = collect($schedule->days ?? []);
        $todaySchedule = $days->firstWhere('day_of_week', $dayOfWeek);
        $todayChatHours = $chatHours->hoursOn($shop, $dayOfWeek);

        if ($todaySchedule && $todaySchedule->is_working_day && $todayChatHours) {
            $config['schedule'] = [
                'start'    => $this->formatTime($shop, $todayChatHours['s']),
                'end'      => $this->formatTime($shop, $todayChatHours['e']),
                'timezone' => $timezone,
            ];
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
        $nextOpening = IsWithinWorkingHours::make()->chatHours()->nextOpening($shop, now());

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
                    'start'       => $shop->organisation->formatClockTime($nextOpening['opens']),
                    'end'         => $shop->organisation->formatClockTime($nextOpening['closes']),
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

    /**
     * Written the way the shop's organisation writes times (Organisation settings › Time format), 08:00 by default.
     */
    private function formatTime(Shop $shop, mixed $time): ?string
    {
        if (!$time) {
            return null;
        }

        return $shop->organisation->formatClockTime(Carbon::parse((string) $time));
    }
}
