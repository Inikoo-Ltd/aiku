<?php

namespace App\Actions\HumanResources\Clocking;

use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Timesheet;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Guest;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Carbon;

class UpdateClockingNotes
{
    use AsAction;

    public function handle(Clocking $clocking, ?string $notes, ?string $clockedAt): Clocking
    {
        $data = [
            'notes' => $notes,
        ];

        if ($clockedAt) {
            $data['clocked_at'] = Carbon::parse($clockedAt);
        }

        $clocking->update($data);
        $clocking->refresh();

        $this->updateTimeTrackerAndTimesheet($clocking);
        $this->hydrateSubjectClockings($clocking);

        return $clocking;
    }

    public function asController(Clocking $clocking, ActionRequest $request)
    {
        $validated = $request->validated();

        $this->handle(
            $clocking,
            $validated['notes'] ?? null,
            $validated['clocked_at'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => __('Notes updated successfully.'),
            'clocking' => $clocking
        ]);
    }

    public function rules(): array
    {
        return [
            'notes'      => ['nullable', 'string', 'max:500'],
            'clocked_at' => ['nullable', 'date'],
        ];
    }

    protected function updateTimeTrackerAndTimesheet(Clocking $clocking): void
    {
        if (!$clocking->timesheet_id) {
            return;
        }

        /** @var Timesheet $timesheet */
        $timesheet = $clocking->timesheet;

        if (!$timesheet) {
            return;
        }

        /** @var TimeTracker|null $timeTracker */
        $timeTracker = TimeTracker::query()
            ->where('timesheet_id', $timesheet->id)
            ->where(function ($q) use ($clocking) {
                $q->where('start_clocking_id', $clocking->id)
                    ->orWhere('end_clocking_id', $clocking->id);
            })
            ->first();

        if ($timeTracker) {
            if ($timeTracker->start_clocking_id === $clocking->id) {
                $timeTracker->starts_at = $clocking->clocked_at;
            }

            if ($timeTracker->end_clocking_id === $clocking->id) {
                $timeTracker->ends_at = $clocking->clocked_at;
            }

            if ($timeTracker->starts_at && $timeTracker->ends_at) {
                $timeTracker->duration = $timeTracker->starts_at->diffInSeconds($timeTracker->ends_at);
            }

            $timeTracker->save();
        }

        $startAt = $timesheet->timeTrackers()->min('starts_at');
        $endAt   = $timesheet->timeTrackers()->max('ends_at');

        $timesheet->update([
            'start_at' => $startAt,
            'end_at'   => $endAt,
        ]);

        TimesheetHydrateTimeTrackers::dispatch($timesheet);
    }

    protected function hydrateSubjectClockings(Clocking $clocking): void
    {
        $subject = $clocking->subject;

        if ($subject instanceof Employee) {
            EmployeeHydrateClockings::dispatch($subject);

            return;
        }

        if ($subject instanceof Guest) {
            GuestHydrateClockings::dispatch($subject);
        }
    }
}
