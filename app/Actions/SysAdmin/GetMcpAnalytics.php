<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\McpRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMcpAnalytics
{
    use AsObject;

    public function handle(Group $group, int $days = 30): array
    {
        $base = McpRequest::where('mcp_requests.group_id', $group->id)
            ->where('mcp_requests.created_at', '>=', now()->subDays($days))
            ->join('users', 'users.id', '=', 'mcp_requests.user_id');

        $totals = (clone $base)
            ->selectRaw('
                count(*) as calls,
                count(*) filter (where is_error) as errors,
                count(distinct user_id) as users,
                round(avg(duration_ms)) as avg_ms,
                count(*) filter (where users.can_use_mcp_sql) as sql_calls,
                count(distinct user_id) filter (where users.can_use_mcp_sql) as sql_users
            ')
            ->first();

        $daily = (clone $base)
            ->selectRaw("
                to_char(mcp_requests.created_at::date, 'YYYY-MM-DD') as date,
                count(*) filter (where not users.can_use_mcp_sql) as tool_calls,
                count(*) filter (where users.can_use_mcp_sql) as sql_calls,
                count(*) filter (where is_error) as errors
            ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topTools = (clone $base)
            ->selectRaw('tool, count(*) as calls, count(*) filter (where is_error) as errors, round(avg(duration_ms)) as avg_ms')
            ->groupBy('tool')
            ->orderByDesc('calls')
            ->limit(10)
            ->get();

        $topUsers = (clone $base)
            ->selectRaw('users.username, bool_or(users.can_use_mcp_sql) as sql_access, count(*) as calls, count(*) filter (where is_error) as errors, max(mcp_requests.created_at) as last_used_at')
            ->groupBy('users.username')
            ->orderByDesc('calls')
            ->limit(10)
            ->get();

        $calls = (int) $totals->calls;

        return [
            'days'       => $days,
            'calls'      => $calls,
            'errors'     => (int) $totals->errors,
            'error_rate' => $calls ? round($totals->errors / $calls * 100, 1) : 0,
            'users'      => (int) $totals->users,
            'avg_ms'     => (int) $totals->avg_ms,
            'sql_calls'  => (int) $totals->sql_calls,
            'sql_users'  => (int) $totals->sql_users,
            'tool_calls' => $calls - (int) $totals->sql_calls,
            'daily'      => $daily,
            'top_tools'  => $topTools,
            'top_users'  => $topUsers,
        ];
    }
}
