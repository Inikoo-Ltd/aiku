<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpSql;

use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMcpSqlTiers
{
    use AsAction;

    /**
     * The tiers a user's MCP SQL runs with, from the job positions on their employee records
     * and the ones given to the user directly. Null means unrestricted: every table and column.
     *
     * @return list<string>|null
     */
    public function handle(User $user): ?array
    {
        if (in_array($user->username, config('mcp_sql_tiers.unrestricted_users'), true)) {
            return null;
        }

        $positions = config('mcp_sql_tiers.positions');

        return $user->employees()->with('jobPositions')->get()
            ->flatMap(fn (Employee $employee) => $employee->jobPositions->pluck('code'))
            ->merge($user->pseudoJobPositions()->pluck('job_positions.code'))
            ->flatMap(fn (string $code) => $positions[$code] ?? [])
            ->push('base')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
