<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUsersInsights
{
    use AsObject;

    public function handle(Group $group, int $days = 30): array
    {
        return Cache::remember(
            "sysadmin-users-insights-{$group->id}-{$days}",
            now()->addMinutes(15),
            fn () => $this->compute($group, $days)
        );
    }

    protected function compute(Group $group, int $days): array
    {
        $base = DB::table('user_requests')
            ->where('user_requests.group_id', $group->id)
            ->where('user_requests.date', '>=', now()->subDays($days));

        $activeToday = DB::table('user_requests')
            ->where('group_id', $group->id)
            ->where('date', '>=', now()->startOfDay())
            ->distinct('user_id')
            ->count('user_id');

        $records = DB::table('user_time_series_records')
            ->join('user_time_series', 'user_time_series.id', '=', 'user_time_series_records.user_time_series_id')
            ->join('users', 'users.id', '=', 'user_time_series.user_id')
            ->where('users.group_id', $group->id)
            ->where('user_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('user_time_series_records.from', '>=', now()->subDays($days));

        $logins = (int) (clone $records)->sum('number_logins');

        $topUsers = (clone $records)
            ->selectRaw('users.username, users.slug, sum(number_requests) as requests, max(user_time_series_records.to) filter (where number_requests > 0) as last_active_at')
            ->groupBy('users.username', 'users.slug')
            ->havingRaw('sum(number_requests) > 0')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $devices = (clone $base)
            ->selectRaw("coalesce(nullif(device, ''), 'unknown') as device, count(*) as requests")
            ->groupBy('device')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        $browsers = (clone $base)
            ->selectRaw("coalesce(nullif(browser, ''), 'unknown') as browser, count(*) as requests")
            ->groupBy('browser')
            ->orderByDesc('requests')
            ->limit(10)
            ->get();

        return [
            'days'          => $days,
            'active_users'  => (int) $group->sysadminStats->number_users_status_active,
            'active_guests' => (int) $group->sysadminStats->number_guests_status_active,
            'active_today'  => $activeToday,
            'logins'        => $logins,
            'top_users'     => $topUsers,
            'devices'       => $devices,
            'browsers'      => $browsers,
        ];
    }
}
