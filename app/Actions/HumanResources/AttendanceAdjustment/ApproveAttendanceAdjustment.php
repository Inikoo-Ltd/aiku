<?php

namespace App\Actions\HumanResources\AttendanceAdjustment;

use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\Models\SysAdmin\Organisation;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ApproveAttendanceAdjustment extends OrgAction
{
    public function handle(AttendanceAdjustment $adjustment): AttendanceAdjustment
    {
        $adjustment->update([
            'status' => AttendanceAdjustmentStatusEnum::APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if ($adjustment->timesheet_id) {
            $timesheet = Timesheet::with('timeTrackers')->find($adjustment->timesheet_id);
            if ($timesheet) {
                DB::transaction(function () use ($timesheet, $adjustment) {
                    $this->correctTimeTrackers($timesheet, $adjustment);

                    $timesheet->update([
                        'start_at' => $adjustment->requested_start_at,
                        'end_at' => $adjustment->requested_end_at,
                    ]);
                });

                TimesheetHydrateTimeTrackers::run($timesheet->id);
            }
        }

        return $adjustment;
    }

    /**
     * The approved times describe the whole day, so they move the first clock-in and the
     * last clock-out and leave any middle break untouched. Correcting the trackers rather
     * than the timesheet columns is what makes the adjustment survive: a later clock-out
     * rewrites the timesheet's end_at from its own clocking, and working_duration is only
     * ever derived from the trackers.
     */
    private function correctTimeTrackers(Timesheet $timesheet, AttendanceAdjustment $adjustment): void
    {
        $timeTrackers = $timesheet->timeTrackers->sortBy('starts_at')->values();

        if ($timeTrackers->isEmpty()) {
            return;
        }

        if ($adjustment->requested_start_at) {
            $first = $timeTrackers->first();
            $first->starts_at = $adjustment->requested_start_at;
            $this->saveWithDuration($first);
        }

        if ($adjustment->requested_end_at) {
            $last = $timeTrackers->last();
            $last->ends_at = $adjustment->requested_end_at;
            $last->status  = TimeTrackerStatusEnum::CLOSED;
            $this->saveWithDuration($last);
        }
    }

    /**
     * A correction that ends before it starts would write a negative duration straight into
     * the hours someone is paid from, so the times are left alone when they do not make sense.
     */
    private function saveWithDuration(TimeTracker $timeTracker): void
    {
        if ($timeTracker->status != TimeTrackerStatusEnum::CLOSED || !$timeTracker->ends_at || !$timeTracker->starts_at) {
            $timeTracker->save();

            return;
        }

        if ($timeTracker->ends_at->lessThanOrEqualTo($timeTracker->starts_at)) {
            return;
        }

        $timeTracker->duration = (int) $timeTracker->starts_at->diffInSeconds($timeTracker->ends_at);
        $timeTracker->save();
    }

    public function asController(Organisation $organisation, AttendanceAdjustment $adjustment, ActionRequest $request): AttendanceAdjustment
    {
        $this->initialisation($organisation, $request);

        return $this->handle($adjustment);
    }

    public function htmlResponse(AttendanceAdjustment $adjustment, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Adjustment request approved.'),
            ]);
    }

    public function jsonResponse(AttendanceAdjustment $adjustment): AttendanceAdjustmentResource
    {
        return AttendanceAdjustmentResource::make($adjustment);
    }
}
