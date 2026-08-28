<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Time trackers whose end falls before their start, from before TimeTracker::normaliseInterval
 * ordered the pair: a clocking arriving out of sequence was taken as the end whatever its time,
 * and Carbon's signed diff turned the gap into a negative duration that
 * TimesheetHydrateTimeTrackers then added into the day's worked time.
 *
 * Ordering the endpoints recovers the real interval - both timestamps are genuine clockings of
 * that person on that day, only their roles were swapped - and the day is re-hydrated after.
 */
class RepairNegativeTimeTrackers
{
    use AsAction;

    public string $commandSignature = 'time-trackers:repair-negative-durations {--execute : Apply the fix. Without this flag, only a report is printed.}';

    public string $commandDescription = 'Find (and optionally fix) time trackers whose end is before their start, which subtract from the worked time of their day.';

    /**
     * @return Collection<int, TimeTracker>
     */
    public function handle(): Collection
    {
        return TimeTracker::where('duration', '<', 0)->orderBy('starts_at')->get();
    }

    /**
     * @param  Collection<int, TimeTracker>  $timeTrackers
     */
    public function repair(Collection $timeTrackers): int
    {
        $timesheetIds = [];

        foreach ($timeTrackers as $timeTracker) {
            $timeTracker->normaliseInterval();

            if ($timeTracker->timesheet_id) {
                $timesheetIds[$timeTracker->timesheet_id] = true;
            }
        }

        foreach (array_keys($timesheetIds) as $timesheetId) {
            TimesheetHydrateTimeTrackers::run($timesheetId);
        }

        return $timeTrackers->count();
    }

    /**
     * Days whose own start and end were written from the wrong-way-round clockings. Ordering the
     * trackers underneath does not touch them, and total_duration is measured straight from them,
     * so the day keeps reporting a negative span until they are realigned to the trackers.
     *
     * @return Collection<int, Timesheet>
     */
    public function invertedTimesheets(): Collection
    {
        return Timesheet::whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->whereColumn('end_at', '<', 'start_at')
            ->get();
    }

    /**
     * @param  Collection<int, Timesheet>  $timesheets
     */
    public function realignTimesheets(Collection $timesheets): int
    {
        $realigned = 0;

        foreach ($timesheets as $timesheet) {
            $bounds = $timesheet->timeTrackers()
                ->selectRaw('min(starts_at) as first_start, max(ends_at) as last_end')
                ->first();

            if (!$bounds?->first_start) {
                continue;
            }

            $timesheet->update([
                'start_at' => $bounds->first_start,
                'end_at'   => $bounds->last_end,
            ]);

            TimesheetHydrateTimeTrackers::run($timesheet->id);
            $realigned++;
        }

        return $realigned;
    }

    public function asCommand(Command $command): int
    {
        $timeTrackers = $this->handle();
        $inverted     = $this->invertedTimesheets();

        if ($timeTrackers->isEmpty() && $inverted->isEmpty()) {
            $command->info('No time trackers with a negative duration, and no days whose start is after their end.');

            return 0;
        }

        if ($timeTrackers->isEmpty()) {
            $command->info($inverted->count().' day(s) still start after they end, so their total duration reads negative.');

            if (!$command->option('execute')) {
                $command->newLine();
                $command->comment('Dry run only - nothing was changed. Re-run with --execute to realign them to their time trackers.');

                return 0;
            }

            $realigned = $this->realignTimesheets($inverted);
            $command->info("Done. Days realigned: {$realigned}.");

            return 0;
        }

        $hours = round($timeTrackers->sum('duration') / -3600, 1);

        $command->info($timeTrackers->count().' time tracker(s) end before they start, '.$hours.' hour(s) subtracted from worked time across '.$timeTrackers->pluck('subject_id')->unique()->count().' employee(s).');
        $command->line('  Oldest: '.$timeTrackers->first()?->starts_at?->toDateString().', newest: '.$timeTrackers->last()?->starts_at?->toDateString());

        if ($inverted->isNotEmpty()) {
            $command->line('  '.$inverted->count().' day(s) also start after they end and will be realigned to their trackers.');
        }

        if (!$command->option('execute')) {
            $command->newLine();
            $command->comment('Dry run only - nothing was changed. Re-run with --execute to order the endpoints and re-hydrate the days.');

            return 0;
        }

        $command->newLine();
        if (!$command->confirm('This will swap the start and end of the trackers above and re-hydrate their timesheets. Continue?')) {
            $command->comment('Aborted.');

            return 0;
        }

        $repaired  = $this->repair($timeTrackers);
        $realigned = $this->realignTimesheets($this->invertedTimesheets());

        $command->newLine();
        $command->info("Done. Repaired: {$repaired}. Days realigned: {$realigned}.");

        return 0;
    }
}
