<?php

namespace App\Actions\HumanResources\WorkSchedule;

use Illuminate\Support\Collection;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Web\Website;

class GetChatConfig
{
    use AsAction;

    public function handle(Website $website): array
    {
        $chatEnabled = $website->settings['enable_chat'] ?? false;

        $config = [
            'is_online'     => false,
            'schedule'      => null,
            'offline_info'  => null,
        ];

        if (!$chatEnabled) {
            return $config;
        }

        $shop = $website->shop;
        if (!$shop) {
            return $config;
        }


        $effective = $shop->getEffectiveWorkSchedule();
        $schedule  = $effective['schedule'];
        $timezone  = $effective['timezone'];

        if (!$schedule) {
            $config['is_online'] = false;

            return $config;
        }

        $config['is_online'] = $schedule->isOpenNow($timezone);

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
                $days,
                $todaySchedule,
                $dayOfWeek,
                $timezone
            );
        }

        return $config;
    }

    private function buildOfflineInfo(
        Collection $days,
        mixed $todaySchedule,
        int $currentDayOfWeek,
        string $timezone
    ): array {
        $isTodayWorkingDay = (bool) ($todaySchedule?->is_working_day ?? false);
        $reason = $isTodayWorkingDay ? 'outside_working_hours' : 'non_working_day';
        $nextWorkingDay = $this->resolveNextWorkingDay($days, $currentDayOfWeek);

        return [
            'reason' => $reason,
            'today'  => [
                'day_of_week' => $currentDayOfWeek,
                'day_name'    => $this->dayNameFromIso($currentDayOfWeek),
                'is_working_day' => $isTodayWorkingDay,
            ],
            'next_opening' => $nextWorkingDay
                ? [
                    'day_of_week' => (int) $nextWorkingDay->day_of_week,
                    'day_name'    => $this->dayNameFromIso((int) $nextWorkingDay->day_of_week),
                    'start'       => $this->formatTime($nextWorkingDay->start_time),
                    'end'         => $this->formatTime($nextWorkingDay->end_time),
                    'timezone'    => $timezone,
                ]
                : null,
        ];
    }

    private function resolveNextWorkingDay(Collection $days, int $currentDayOfWeek): mixed
    {
        for ($offset = 1; $offset <= 7; $offset++) {
            $targetDay = (($currentDayOfWeek - 1 + $offset) % 7) + 1;
            $candidate = $days->firstWhere('day_of_week', $targetDay);

            if ($candidate && $candidate->is_working_day) {
                return $candidate;
            }
        }

        return null;
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
