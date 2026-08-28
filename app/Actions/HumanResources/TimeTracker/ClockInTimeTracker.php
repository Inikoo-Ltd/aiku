<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ClockInTimeTracker
{
    use AsAction;

    public function handle(TimeTracker $timeTracker, Carbon $clockedAtUtc, int $generatorId, string $generatorType): TimeTracker
    {
        if ($timeTracker->start_clocking_id !== null) {
            throw ValidationException::withMessages([
                'time_tracker' => __('This time tracker already has a clock in.')
            ]);
        }

        $clocking = Clocking::query()->create([
            'group_id'        => $timeTracker->timesheet->group_id,
            'organisation_id' => $timeTracker->timesheet->organisation_id,
            'workplace_id'    => $timeTracker->workplace_id,
            'timesheet_id'    => $timeTracker->timesheet_id,
            'time_tracker_id' => $timeTracker->id,
            'type'            => ClockingTypeEnum::MANUAL,
            'subject_type'    => $timeTracker->subject_type,
            'subject_id'      => $timeTracker->subject_id,
            'clocked_at'      => $clockedAtUtc,
            'generator_type'  => $generatorType,
            'generator_id'    => $generatorId,
        ]);

        $timeTracker->update([
            'start_clocking_id' => $clocking->id,
            'starts_at'         => $clockedAtUtc,
        ]);

        if ($timeTracker->ends_at) {
            $timeTracker->update(['status' => TimeTrackerStatusEnum::CLOSED]);
            $timeTracker->normaliseInterval();
        }

        $this->rehydrateTimesheet($timeTracker->timesheet);

        if ($timeTracker->subject instanceof Guest) {
            GuestHydrateClockings::dispatch($timeTracker->subject);
        } else {
            EmployeeHydrateClockings::dispatch($timeTracker->subject);
        }

        return $timeTracker->refresh();
    }

    private function rehydrateTimesheet(Timesheet $timesheet): void
    {
        $timesheet->refresh();
        $remaining = $timesheet->timeTrackers()->orderBy('starts_at')->get();

        $timesheet->update([
            'start_at' => $remaining->first()?->starts_at,
            'end_at'   => $remaining->filter(fn (TimeTracker $timeTracker) => $timeTracker->ends_at)->last()?->ends_at,
        ]);

        TimesheetHydrateTimeTrackers::run($timesheet->id);
    }

    public function rules(): array
    {
        return [
            'clocked_at_time' => ['required', 'date_format:H:i:s'],
            'timezone'        => ['required', 'timezone'],
        ];
    }

    public function asController(TimeTracker $timeTracker, ActionRequest $request)
    {
        $timeTracker->loadMissing('timesheet', 'subject');
        if (!$request->user()->authTo("human-resources.{$timeTracker->timesheet->organisation_id}.edit")) {
            abort(403);
        }

        $validatedData = $request->validated();

        $referenceDate = $timeTracker->ends_at
            ? $timeTracker->ends_at->copy()->setTimezone($validatedData['timezone'])
            : now($validatedData['timezone']);

        $clockedAtLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $referenceDate->format('Y-m-d').' '.$validatedData['clocked_at_time'],
            $validatedData['timezone']
        );

        return $this->handle(
            $timeTracker,
            $clockedAtLocal->utc(),
            $request->user()->id,
            class_basename($request->user()::class)
        );
    }

    public function jsonResponse(TimeTracker $timeTracker)
    {
        return [
            'success'         => true,
            'message'         => __('Clock in added successfully.'),
            'time_tracker_id' => $timeTracker->id,
        ];
    }
}
