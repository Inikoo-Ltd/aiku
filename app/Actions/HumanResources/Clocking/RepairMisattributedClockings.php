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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A user accumulates one Employee record per employment they've ever had. Before the fix in
 * App\Actions\SysAdmin\User\GetUserCurrentEmployee, QR-code-machine clocking resolved the
 * employee via an unordered `$user->employees->first()`, which could just as easily land on a
 * past, `left` employment as the current one - so some clockings/timesheets ended up recorded
 * against the wrong (old) Employee record instead of the currently working one.
 *
 * The signature of a misattribution is the subject employment being one the person had already
 * left, not the organisation the clocking carries: staff routinely clock on a machine belonging
 * to another organisation, and the machine's organisation is what StoreClocking records, so an
 * organisation mismatch on its own is normal and must never be "repaired".
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

    public string $commandSignature = 'clocking:repair-misattributed {--execute : Actually move the data. Without this flag, only a report is printed.} {--weeks=6 : How far back to look.}';

    public string $commandDescription = 'Find (and optionally fix) clockings recorded against the wrong Employee record for a user who has more than one (e.g. after moving organisations).';

    private Carbon $since;

    /**
     * @return array<int, array{wrong_employee_id: int, correct_employee_id: int, clocking_ids: array<int, int>}>
     */
    public function handle(int $weeks = 6): array
    {
        $this->since = now()->subWeeks($weeks);

        return $this->detectMisattributedPairs()->map(function (array $pair) {
            $clockingIds = $this->clockingsFor($pair['wrong'])->pluck('id')->all();

            return [
                'wrong_employee_id'   => $pair['wrong']->id,
                'correct_employee_id' => $pair['correct']->id,
                'clocking_ids'        => $clockingIds,
            ];
        })
            ->filter(fn (array $row) => count($row['clocking_ids']) > 0)
            ->values()
            ->all();
    }

    /**
     * Employments the person had already left that still carry machine clockings, paired with the
     * employment those clockings belong to. Both the cross-organisation move (old AW record, new
     * Aroma one) and the rehire within one organisation have this same shape.
     */
    private function detectMisattributedPairs(): Collection
    {
        $leftEmployeeIds = Clocking::query()
            ->join('employees', function ($join) {
                $join->on('employees.id', '=', 'clockings.subject_id')
                    ->where('clockings.subject_type', '=', 'Employee');
            })
            ->where('clockings.type', 'clocking-machine')
            ->whereNull('clockings.source_id')
            ->where('clockings.clocked_at', '>=', $this->since)
            ->where('employees.state', EmployeeStateEnum::LEFT->value)
            ->distinct()
            ->pluck('clockings.subject_id');

        $pairs = collect();

        foreach ($leftEmployeeIds as $employeeId) {
            $wrong = Employee::find($employeeId);
            $correct = $wrong ? $this->findActiveEmployment($wrong) : null;

            if ($correct) {
                $pairs->push(['wrong' => $wrong, 'correct' => $correct]);
            }
        }

        return $pairs;
    }

    /**
     * The employment the clockings should have gone to: another employment of the same user that
     * is still active, preferring one in the same organisation, then the most recent. This mirrors
     * how App\Actions\SysAdmin\User\GetUserCurrentEmployee resolves it for new clockings.
     */
    private function findActiveEmployment(Employee $wrong): ?Employee
    {
        $userIds = DB::table('user_has_models')
            ->where('model_type', 'Employee')->where('model_id', $wrong->id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return null;
        }

        $candidates = Employee::whereIn('id', function ($query) use ($userIds) {
            $query->select('model_id')->from('user_has_models')
                ->whereIn('user_id', $userIds)->where('model_type', 'Employee');
        })
            ->whereIn('state', [EmployeeStateEnum::WORKING->value, EmployeeStateEnum::LEAVING->value])
            ->orderByDesc('id')
            ->get();

        return $candidates->firstWhere('organisation_id', $wrong->organisation_id) ?? $candidates->first();
    }

    private function clockingsFor(Employee $wrong)
    {
        $query = Clocking::where('subject_type', 'Employee')->where('subject_id', $wrong->id)
            ->where('type', 'clocking-machine')
            ->whereNull('source_id')
            ->where('clocked_at', '>=', $this->since);

        if ($wrong->employment_end_at) {
            $query->where('clocked_at', '>', $wrong->employment_end_at);
        }

        return $query->orderBy('clocked_at')->get();
    }

    /**
     * @param  array{wrong_employee_id: int, correct_employee_id: int, clocking_ids: array<int, int>}  $pair
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

    /**
     * Days stranded on a closed employment with nothing left on them: no clockings, no attendance
     * adjustment, no worked time, and only empty time trackers - husks left behind when a clocking
     * is unlinked and moved. They are what keeps closed employments showing on the HR clocking
     * lists, so they are reported alongside the misattributions and cleared with them.
     *
     * @return Collection<int, int>
     */
    public function strandedEmptyTimesheetIds(): Collection
    {
        return Timesheet::query()
            ->join('employees', 'employees.id', '=', 'timesheets.subject_id')
            ->where('timesheets.subject_type', 'Employee')
            ->where('employees.state', EmployeeStateEnum::LEFT->value)
            ->where('timesheets.date', '>=', $this->since)
            ->whereNull('timesheets.source_id')
            ->where('timesheets.working_duration', 0)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('clockings')
                ->whereColumn('clockings.timesheet_id', 'timesheets.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('attendance_adjustments')
                ->whereColumn('attendance_adjustments.timesheet_id', 'timesheets.id'))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('time_trackers')
                ->whereColumn('time_trackers.timesheet_id', 'timesheets.id')
                ->where(fn ($carries) => $carries->whereNotNull('starts_at')
                    ->orWhereNotNull('ends_at')
                    ->orWhereNotNull('start_clocking_id')
                    ->orWhereNotNull('end_clocking_id')
                    ->orWhere('duration', '>', 0)))
            ->pluck('timesheets.id');
    }

    /**
     * Timesheets delete for real while time trackers only soft delete, so a husk keeps its foreign
     * key on the timesheet long after it stops being counted - hence withTrashed()/forceDelete().
     *
     * @param  Collection<int, int>  $timesheetIds
     */
    public function deleteStrandedTimesheets(Collection $timesheetIds): int
    {
        return DB::transaction(function () use ($timesheetIds) {
            TimeTracker::withTrashed()->whereIn('timesheet_id', $timesheetIds)->forceDelete();

            return Timesheet::whereIn('id', $timesheetIds)->delete();
        });
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
        $weeks = (int) $command->option('weeks');
        $pairs = $this->handle($weeks);
        $stranded = $this->strandedEmptyTimesheetIds();

        if (empty($pairs) && $stranded->isEmpty()) {
            $command->info("Nothing to repair in the last {$weeks} week(s).");

            return 0;
        }

        $totalClockings = array_sum(array_map(fn (array $p) => count($p['clocking_ids']), $pairs));

        $command->info(count($pairs).' employee pair(s) found, '.$totalClockings.' clocking(s) affected:');
        $command->newLine();

        foreach ($pairs as $pair) {
            $wrong = Employee::find($pair['wrong_employee_id']);
            $correct = Employee::find($pair['correct_employee_id']);
            $count = count($pair['clocking_ids']);

            $command->line("  {$wrong->slug} (#{$wrong->id}, org={$wrong->organisation_id}, {$wrong->state->value}) -> {$correct->slug} (#{$correct->id}, org={$correct->organisation_id}, {$correct->state->value}) : {$count} clocking(s)");
        }

        if ($stranded->isNotEmpty()) {
            $command->newLine();
            $command->line('  '.$stranded->count().' empty day(s) stranded on closed employments will be removed (no clockings, no worked time).');
        }

        if (!$command->option('execute')) {
            $command->newLine();
            $command->comment('Dry run only - nothing was changed. Re-run with --execute to apply the fix.');

            return 0;
        }

        $command->newLine();
        if (!$command->confirm('This will move the clockings listed above to the correct employee, re-hydrate their timesheets and remove the empty stranded days. Continue?')) {
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

        $strandedAfterRepair = $this->strandedEmptyTimesheetIds();
        $deleted = $strandedAfterRepair->isEmpty() ? 0 : $this->deleteStrandedTimesheets($strandedAfterRepair);

        $command->newLine();
        $command->info("Done. Total moved: {$totalMoved}. Empty stranded days removed: {$deleted}.");

        return 0;
    }
}
