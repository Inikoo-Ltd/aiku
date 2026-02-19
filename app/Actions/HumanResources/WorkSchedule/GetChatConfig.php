<?php

namespace App\Actions\HumanResources\WorkSchedule;

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
            'is_online' => false,
            'schedule'  => null,
        ];

        if (!$chatEnabled) {
            return $config;
        }

        $shop = $website->shop;
        if (!$shop) {
            return $config;
        }


        $effective = $shop->getEffectiveWorkSchedule();
        $schedule = $effective['schedule'];
        $timezone = $effective['timezone'];

        if ($schedule) {

            $config['is_online'] = $schedule->isOpenNow($timezone);


            $now = Carbon::now($timezone);
            // dayOfWeekIso (1=Mon...7=Sun)
            $dayOfWeek = $now->dayOfWeekIso;

            $todaySchedule = $schedule->days->where('day_of_week', $dayOfWeek)->first();
            $startTime = Carbon::parse($todaySchedule->start_time)
                ->format('H:i:s');

            $endTime = Carbon::parse($todaySchedule->end_time)
                ->format('H:i:s');

            if ($todaySchedule && $todaySchedule->is_working_day) {
                $config['schedule'] = [
                    'start'    => $startTime,
                    'end'      => $endTime,
                    'timezone' => $timezone,
                ];
            }
        } else {
            $config['is_online'] = false;
        }

        return $config;
    }
}
