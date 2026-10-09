<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncLeaveCoverRoles
{
    use AsAction;

    public string $commandSignature = 'hr:sync-leave-cover-roles';

    public function handle(Employee $coverEmployee): void
    {
        foreach ($coverEmployee->users as $user) {
            SyncRolesFromJobPositions::run($user);
        }
    }

    /**
     * Covers gain the absent employee's roles when the leave starts and give them back when it ends.
     * ponytail: re-syncs every cover running or ended within the last week on each run, so a missed
     * run still hands roles back; track a synced flag per leave if this list ever gets long.
     */
    public function asCommand(Command $command): int
    {
        Employee::whereIn(
            'id',
            Leave::where('status', LeaveStatusEnum::APPROVED)
                ->where('cover_has_permissions', true)
                ->whereDate('start_date', '<=', now()->toDateString())
                ->whereDate('end_date', '>=', now()->subWeek()->toDateString())
                ->select('cover_employee_id')
        )->each(function (Employee $coverEmployee) {
            setPermissionsTeamId($coverEmployee->group_id);
            $this->handle($coverEmployee);
        });

        return 0;
    }
}
