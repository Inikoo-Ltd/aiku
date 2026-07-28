<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

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

        $logins = DB::table('user_logins')
            ->join('users', 'users.id', '=', 'user_logins.user_id')
            ->where('users.group_id', $group->id)
            ->where('user_logins.date', '>=', now()->subDays($days))
            ->count();

        $topUsers = (clone $base)
            ->join('users', 'users.id', '=', 'user_requests.user_id')
            ->selectRaw('users.username, users.slug, count(*) as requests, max(user_requests.date) as last_active_at')
            ->groupBy('users.username', 'users.slug')
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
