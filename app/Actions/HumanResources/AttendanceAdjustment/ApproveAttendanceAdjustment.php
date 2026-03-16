<?php

namespace App\Actions\HumanResources\AttendanceAdjustment;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\Models\SysAdmin\Organisation;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
            $timesheet = Timesheet::find($adjustment->timesheet_id);
            if ($timesheet) {
                $timesheet->update([
                    'start_at' => $adjustment->requested_start_at,
                    'end_at' => $adjustment->requested_end_at,
                ]);
            }
        }

        return $adjustment;
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
