<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserRequestAnalytics
{
    use AsObject;

    public function handle(Group $group, int $days = 30): array
    {
        $cached = Cache::remember(
            "sysadmin-analytics-dashboard:{$group->id}",
            300,
            fn () => $this->compute($group, $days)
        );

        $online = $this->computeOnlineNow($group);
        $cached['online_now']   = $online['online_now'];
        $cached['online_count'] = $online['online_count'];

        return $cached;
    }

    protected function computeOnlineNow(Group $group): array
    {
        return Cache::remember(
            "sysadmin-analytics-dashboard:{$group->id}:online-now",
            60,
            function () use ($group) {
                $online = DB::table('user_requests')
                    ->join('users', 'users.id', '=', 'user_requests.user_id')
                    ->where('user_requests.group_id', $group->id)
                    ->where('user_requests.date', '>=', now()->subMinutes(15))
                    ->selectRaw('users.username, users.slug, max(user_requests.date) as last_seen_at')
                    ->selectRaw("(select route_name from user_requests ur2 where ur2.user_id = user_requests.user_id and ur2.group_id = ? order by ur2.date desc limit 1) as last_route_name", [$group->id])
                    ->selectRaw('(select count(*) from user_requests ur3 where ur3.user_id = user_requests.user_id and ur3.group_id = ? and ur3.date >= ?) as requests_today', [$group->id, now()->startOfDay()])
                    ->groupBy('users.username', 'users.slug', 'user_requests.user_id')
                    ->orderByDesc('last_seen_at')
                    ->limit(15)
                    ->get();

                $count = DB::table('user_requests')
                    ->where('group_id', $group->id)
                    ->where('date', '>=', now()->subMinutes(15))
                    ->distinct('user_id')
                    ->count('user_id');

                return [
                    'online_now'   => $online,
                    'online_count' => $count,
                ];
            }
        );
    }

    protected function compute(Group $group, int $days): array
    {
        $base = DB::table('user_requests')
            ->where('user_requests.group_id', $group->id)
            ->where('user_requests.date', '>=', now()->subDays($days));

        $requestsToday = DB::table('user_requests')
            ->where('group_id', $group->id)
            ->where('date', '>=', now()->startOfDay())
            ->count();

        $activeUsers = (clone $base)->distinct('user_id')->count('user_id');

        $records = DB::table('user_time_series_records')
            ->join('user_time_series', 'user_time_series.id', '=', 'user_time_series_records.user_time_series_id')
            ->join('users', 'users.id', '=', 'user_time_series.user_id')
            ->where('users.group_id', $group->id)
            ->where('user_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('user_time_series_records.from', '>=', now()->subDays($days));

        $logins = (int) (clone $records)->sum('number_logins');

        $requestsPerDay = $this->fillDays(
            (clone $base)
                ->selectRaw('date::date as day, count(*) as count')
                ->groupBy('day')
                ->pluck('count', 'day'),
            $days
        );

        $loginsPerDay = $this->fillDays(
            (clone $records)
                ->selectRaw('user_time_series_records.from::date as day, sum(number_logins) as count')
                ->groupBy('day')
                ->pluck('count', 'day'),
            $days
        );

        $topUsers = (clone $base)
            ->join('users', 'users.id', '=', 'user_requests.user_id')
            ->selectRaw('users.username, users.slug, count(*) as requests')
            ->groupBy('users.username', 'users.slug')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $topModules = (clone $base)
            ->selectRaw("coalesce(nullif(split_part(route_name, '.', 2), ''), 'other') as name, count(*) as count")
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $devices = (clone $base)
            ->selectRaw("coalesce(nullif(device, ''), 'unknown') as name, count(*) as count")
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $browsers = (clone $base)
            ->selectRaw("coalesce(nullif(browser, ''), 'unknown') as name, count(*) as count")
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'requests_today'   => $requestsToday,
            'active_users_30d' => $activeUsers,
            'logins_30d'       => $logins,
            'requests_per_day' => $requestsPerDay,
            'logins_per_day'   => $loginsPerDay,
            'top_users_30d'    => $topUsers,
            'top_modules_30d'  => $topModules,
            'devices_30d'      => $devices,
            'browsers_30d'     => $browsers,
        ];
    }

    protected function fillDays(\Illuminate\Support\Collection $byDay, int $days): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $result[] = [
                'date'  => $date,
                'count' => (int) ($byDay[$date] ?? 0),
            ];
        }

        return $result;
    }
}
