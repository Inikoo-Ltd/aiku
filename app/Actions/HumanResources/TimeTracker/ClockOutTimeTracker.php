<?php

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\TimeTracker;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ClockOutTimeTracker
{
    use AsAction;

    public function handle(TimeTracker $timeTracker, Carbon $clockedAtUtc, int $generatorId, string $generatorType): TimeTracker
    {
        if ($timeTracker->status !== TimeTrackerStatusEnum::OPEN) {
            throw ValidationException::withMessages([
                'time_tracker' => __('Only open time tracker can be clocked out.')
            ]);
        }

        $clocking = Clocking::query()->create([
            'group_id'      => $timeTracker->timesheet->group_id,
            'organisation_id' => $timeTracker->timesheet->organisation_id,
            'workplace_id'  => $timeTracker->workplace_id,
            'timesheet_id'  => $timeTracker->timesheet_id,
            'type'          => ClockingTypeEnum::MANUAL,
            'subject_type'  => $timeTracker->subject_type,
            'subject_id'    => $timeTracker->subject_id,
            'clocked_at'    => $clockedAtUtc,
            'generator_type' => $generatorType,
            'generator_id'  => $generatorId,
        ]);

        CloseTimeTracker::make()->action($timeTracker, $clocking, []);

        if ($timeTracker->subject instanceof Guest) {
            GuestHydrateClockings::dispatch($timeTracker->subject);
        } else {
            EmployeeHydrateClockings::dispatch($timeTracker->subject);
        }

        return $timeTracker->refresh();
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

        $referenceDate = $timeTracker->starts_at
            ? $timeTracker->starts_at->copy()->setTimezone($validatedData['timezone'])
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
            'success' => true,
            'message' => __('Clock out added successfully.'),
            'time_tracker_id' => $timeTracker->id,
        ];
    }
}
