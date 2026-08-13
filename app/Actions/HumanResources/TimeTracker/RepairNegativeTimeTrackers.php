<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
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

    public function asCommand(Command $command): int
    {
        $timeTrackers = $this->handle();

        if ($timeTrackers->isEmpty()) {
            $command->info('No time trackers with a negative duration found.');

            return 0;
        }

        $hours = round($timeTrackers->sum('duration') / -3600, 1);

        $command->info($timeTrackers->count().' time tracker(s) end before they start, '.$hours.' hour(s) subtracted from worked time across '.$timeTrackers->pluck('subject_id')->unique()->count().' employee(s).');
        $command->line('  Oldest: '.$timeTrackers->first()?->starts_at?->toDateString().', newest: '.$timeTrackers->last()?->starts_at?->toDateString());

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

        $repaired = $this->repair($timeTrackers);

        $command->newLine();
        $command->info("Done. Repaired: {$repaired}.");

        return 0;
    }
}
