<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\SysAdmin\Organisation;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ApproveLeave extends OrgAction
{
    public function handle(Leave $leave): Leave
    {
        $leave->update([
            'status' => LeaveStatusEnum::APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $balance = EmployeeLeaveBalance::where('employee_id', $leave->employee_id)
            ->where('year', now()->year)
            ->first();

        if ($balance) {
            $field = match ($leave->type->value) {
                'annual' => 'annual_used',
                'medical' => 'medical_used',
                'unpaid' => 'unpaid_used',
                default => null,
            };

            if ($field) {
                $balance->increment($field, $leave->duration_days);
            }
        }

        return $leave;
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leave);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave request approved.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
