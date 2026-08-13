<?php

namespace App\Actions\HumanResources\Clocking;

use App\Actions\HumanResources\Timesheet\GetTimesheet;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\HumanResources\TimeTracker\AddClockingToTimeTracker;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Timesheet;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A user accumulates one Employee record per organisation they've ever worked for. Before the
 * fix in App\Actions\SysAdmin\User\GetUserCurrentEmployee, QR-code-machine clocking resolved
 * the employee via an unordered `$user->employees->first()`, which could just as easily land on
 * a past, `left` employment as the current one - so some clockings/timesheets ended up recorded
 * against the wrong (old) Employee record instead of the currently working one.
 *
 * This finds every such case and, on request, moves the misattributed clockings to the correct
 * employee/timesheet, re-pairing their time trackers and re-hydrating both sides.
 *
 * Only `type = clocking-machine` clockings are touched - manual entries and older imported data
 * are left alone, since they aren't produced by the buggy resolution path.
 */
class RepairMisattributedClockings
{
    use AsAction;

    public string $commandSignature = 'clocking:repair-misattributed {--execute : Actually move the data. Without this flag, only a report is printed.}';

    public string $commandDescription = 'Find (and optionally fix) clockings recorded against the wrong Employee record for a user who has more than one (e.g. after moving organisations).';

    /**
     * @return array<int, array{wrong_employee_id: int, correct_employee_id: int, mode: string, clocking_ids: array<int, int>}>
     */
    public function handle(): array
    {
        $pairs = $this->detectCrossOrgPairs()->concat($this->detectSameOrgPairs());

        return $pairs->map(function (array $pair) {
            $clockingIds = $this->clockingsFor($pair['wrong'], $pair['correct'], $pair['mode'])->pluck('id')->all();

            return [
                'wrong_employee_id'   => $pair['wrong']->id,
                'correct_employee_id' => $pair['correct']->id,
                'mode'                => $pair['mode'],
                'clocking_ids'        => $clockingIds,
            ];
        })
            ->filter(fn (array $row) => count($row['clocking_ids']) > 0)
            ->values()
            ->all();
    }

    /**
     * Employees with a Clocking recorded under an organisation other than their own, where a
     * currently active (working/leaving) sibling Employee - same user, that other organisation -
     * exists to receive it.
     */
    private function detectCrossOrgPairs(): Collection
    {
        $mismatched = Clocking::query()
            ->join('employees', function ($join) {
                $join->on('employees.id', '=', 'clockings.subject_id')
                    ->where('clockings.subject_type', '=', 'Employee');
            })
            ->where('clockings.type', 'clocking-machine')
            ->whereColumn('clockings.organisation_id', '!=', 'employees.organisation_id')
            ->select(['clockings.subject_id as employee_id', 'clockings.organisation_id as clocking_org_id'])
            ->distinct()
            ->get();

        $pairs = collect();

        foreach ($mismatched as $row) {
            $wrong = Employee::find($row->employee_id);
            $correct = $this->findSiblingEmployee($wrong, (int) $row->clocking_org_id);

            if ($correct) {
                $pairs->push(['wrong' => $wrong, 'correct' => $correct, 'mode' => 'cross_org']);
            }
        }

        return $pairs;
    }

    /**
     * Users with two Employee records in the SAME organisation - one `left`, one active - where
     * the `left` one still has clockings dated after its own employment actually ended.
     */
    private function detectSameOrgPairs(): Collection
    {
        $userIds = DB::table('user_has_models')
            ->where('model_type', 'Employee')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->pluck('user_id');

        $pairs = collect();

        foreach ($userIds as $userId) {
            $employeeIds = DB::table('user_has_models')
                ->where('model_type', 'Employee')->where('user_id', $userId)
                ->pluck('model_id');

            $employees = Employee::whereIn('id', $employeeIds)->get();

            foreach ($employees->groupBy('organisation_id') as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                $left = $group->firstWhere('state', EmployeeStateEnum::LEFT);
                $active = $group->first(fn (Employee $e) => in_array($e->state->value, ['working', 'leaving']));

                if (!$left || !$active || !$left->employment_end_at) {
                    continue;
                }

                $hasStrayClockings = Clocking::where('subject_type', 'Employee')->where('subject_id', $left->id)
                    ->where('type', 'clocking-machine')
                    ->where('clocked_at', '>', $left->employment_end_at)
                    ->exists();

                if ($hasStrayClockings) {
                    $pairs->push(['wrong' => $left, 'correct' => $active, 'mode' => 'same_org']);
                }
            }
        }

        return $pairs;
    }

    private function findSiblingEmployee(Employee $wrong, int $targetOrganisationId): ?Employee
    {
        $userIds = DB::table('user_has_models')
            ->where('model_type', 'Employee')->where('model_id', $wrong->id)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $candidate = Employee::whereIn('id', function ($query) use ($userId) {
                $query->select('model_id')->from('user_has_models')
                    ->where('user_id', $userId)->where('model_type', 'Employee');
            })
                ->where('organisation_id', $targetOrganisationId)
                ->whereIn('state', ['working', 'leaving'])
                ->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function clockingsFor(Employee $wrong, Employee $correct, string $mode)
    {
        $query = Clocking::where('subject_type', 'Employee')->where('subject_id', $wrong->id)
            ->where('type', 'clocking-machine');

        if ($mode === 'cross_org') {
            $query->where('organisation_id', $correct->organisation_id);
        } else {
            $query->where('clocked_at', '>', $wrong->employment_end_at);
        }

        return $query->orderBy('clocked_at')->get();
    }

    /**
     * @param  array{wrong_employee_id: int, correct_employee_id: int, mode: string, clocking_ids: array<int, int>}  $pair
     */
    public function repairPair(array $pair): int
    {
        $correct = Employee::findOrFail($pair['correct_employee_id']);
        $clockings = Clocking::whereIn('id', $pair['clocking_ids'])->orderBy('clocked_at')->get();

        $movedCount = 0;
        $touchedOldTimesheetIds = [];
        $touchedNewTimesheetIds = [];

        DB::transaction(function () use ($clockings, $correct, &$movedCount, &$touchedOldTimesheetIds, &$touchedNewTimesheetIds) {
            $timezone = $correct->organisation->timezone->name ?? config('app.timezone');

            foreach ($clockings as $clocking) {
                if ($clocking->timesheet_id) {
                    $touchedOldTimesheetIds[$clocking->timesheet_id] = true;
                }

                TimeTracker::where('start_clocking_id', $clocking->id)
                    ->update(['start_clocking_id' => null, 'starts_at' => null]);
                TimeTracker::where('end_clocking_id', $clocking->id)
                    ->update(['end_clocking_id' => null, 'ends_at' => null, 'duration' => null, 'status' => TimeTrackerStatusEnum::OPEN]);
                TimeTracker::whereNull('start_clocking_id')->whereNull('end_clocking_id')
                    ->where('timesheet_id', $clocking->timesheet_id)
                    ->delete();

                $localDate = $clocking->clocked_at->copy()->setTimezone($timezone);
                $newTimesheet = GetTimesheet::run($correct, $localDate);
                $touchedNewTimesheetIds[$newTimesheet->id] = true;

                $clocking->subject_id = $correct->id;
                $clocking->organisation_id = $correct->organisation_id;
                $clocking->timesheet_id = $newTimesheet->id;
                $clocking->time_tracker_id = null;
                $clocking->generator_type = 'Employee';
                $clocking->generator_id = $correct->id;
                $clocking->save();

                AddClockingToTimeTracker::run($newTimesheet, $clocking->fresh());
                $movedCount++;
            }
        });

        foreach (array_keys($touchedOldTimesheetIds) as $timesheetId) {
            $this->rehydrateOrCleanTimesheet($timesheetId);
        }

        foreach (array_keys($touchedNewTimesheetIds) as $timesheetId) {
            TimesheetHydrateTimeTrackers::run($timesheetId);
        }

        return $movedCount;
    }

    private function rehydrateOrCleanTimesheet(int $timesheetId): void
    {
        $timesheet = Timesheet::find($timesheetId);
        if (!$timesheet) {
            return;
        }

        $remaining = $timesheet->timeTrackers()->orderBy('starts_at')->get();
        $timesheet->update([
            'start_at' => $remaining->first()?->starts_at,
            'end_at'   => $remaining->filter(fn (TimeTracker $t) => $t->ends_at)->last()?->ends_at,
        ]);

        TimesheetHydrateTimeTrackers::run($timesheet->id);

        $timesheet->refresh();

        // If nothing is left on this day for the old employee, don't leave a stale duration
        // behind from before the move - the hydrator only recomputes durations when there is
        // at least one closed time tracker, so an emptied-out day needs an explicit reset.
        $stillHasData = $timesheet->timeTrackers()->exists() || Clocking::where('timesheet_id', $timesheet->id)->exists();

        if (!$stillHasData && ($timesheet->working_duration > 0 || $timesheet->breaks_duration > 0 || $timesheet->start_at || $timesheet->end_at)) {
            $timesheet->update([
                'start_at'         => null,
                'end_at'           => null,
                'working_duration' => 0,
                'breaks_duration'  => 0,
                'total_duration'   => 0,
            ]);
        }
    }

    public function asCommand(Command $command): int
    {
        $pairs = $this->handle();

        if (empty($pairs)) {
            $command->info('No misattributed clockings found.');

            return 0;
        }

        $totalClockings = array_sum(array_map(fn (array $p) => count($p['clocking_ids']), $pairs));

        $command->info(count($pairs).' employee pair(s) found, '.$totalClockings.' clocking(s) affected:');
        $command->newLine();

        foreach ($pairs as $pair) {
            $wrong = Employee::find($pair['wrong_employee_id']);
            $correct = Employee::find($pair['correct_employee_id']);
            $count = count($pair['clocking_ids']);

            $command->line("  {$wrong->slug} (#{$wrong->id}, org={$wrong->organisation_id}, {$wrong->state->value}) -> {$correct->slug} (#{$correct->id}, org={$correct->organisation_id}, {$correct->state->value}) [{$pair['mode']}] : {$count} clocking(s)");
        }

        if (!$command->option('execute')) {
            $command->newLine();
            $command->comment('Dry run only - nothing was changed. Re-run with --execute to apply the fix.');

            return 0;
        }

        $command->newLine();
        if (!$command->confirm('This will move the clockings listed above to the correct employee and re-hydrate their timesheets. Continue?')) {
            $command->comment('Aborted.');

            return 0;
        }

        $command->newLine();
        $totalMoved = 0;

        foreach ($pairs as $pair) {
            $wrong = Employee::find($pair['wrong_employee_id']);
            $correct = Employee::find($pair['correct_employee_id']);

            $moved = $this->repairPair($pair);
            $totalMoved += $moved;

            $command->line("  {$wrong->slug} -> {$correct->slug}: moved {$moved} clocking(s)");
        }

        $command->newLine();
        $command->info("Done. Total moved: {$totalMoved}.");

        return 0;
    }
}
