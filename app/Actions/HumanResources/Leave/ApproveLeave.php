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
        $balanceYear = $leave->start_date?->year ?? now()->year;

        $leave->update([
            'status' => LeaveStatusEnum::APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $balance = EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_id' => $leave->employee_id,
                'year'        => $balanceYear,
            ],
            [
                'annual_days'   => $leave->employee->organisation->getDefaultAnnualLeaveDays(),
                'annual_used'   => 0,
                'medical_days'  => 0,
                'medical_used'  => 0,
                'unpaid_days'   => 0,
                'unpaid_used'   => 0,
            ]
        );

        $field = match ($leave->type->value) {
            'annual' => 'annual_used',
            'medical' => 'medical_used',
            'unpaid' => 'unpaid_used',
            default => null,
        };

        if ($field) {
            $deduction = $leave->is_half_day ? 0.5 : $leave->duration_days;
            $balance->increment($field, $deduction);
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
