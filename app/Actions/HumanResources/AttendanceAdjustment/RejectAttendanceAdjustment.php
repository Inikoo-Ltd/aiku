<?php

namespace App\Actions\HumanResources\AttendanceAdjustment;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Attendance\AttendanceAdjustmentStatusEnum;
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\Models\SysAdmin\Organisation;
use App\Models\HumanResources\AttendanceAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RejectAttendanceAdjustment extends OrgAction
{
    public function handle(AttendanceAdjustment $adjustment, string $rejectionReason): AttendanceAdjustment
    {
        $adjustment->update([
            'status' => AttendanceAdjustmentStatusEnum::REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_comment' => $rejectionReason,
        ]);

        return $adjustment;
    }

    public function rules(): array
    {
        return [
            'approval_comment' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(Organisation $organisation, AttendanceAdjustment $adjustment, ActionRequest $request): AttendanceAdjustment
    {
        $this->initialisation($organisation, $request);

        return $this->handle($adjustment, $this->validatedData['approval_comment']);
    }

    public function htmlResponse(AttendanceAdjustment $adjustment, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Adjustment request rejected.'),
            ]);
    }

    public function jsonResponse(AttendanceAdjustment $adjustment): AttendanceAdjustmentResource
    {
        return AttendanceAdjustmentResource::make($adjustment);
    }
}
