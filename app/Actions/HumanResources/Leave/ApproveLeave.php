<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\SysAdmin\Organisation;
use App\Services\HumanResources\LeaveTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ApproveLeave extends OrgAction
{
    public function handle(Leave $leave): Leave
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'You are not authorized to approve this leave at this level.');
        }

        $approvalLevel = $leave->approvalLevelForUser($user);
        if ($approvalLevel === null) {
            abort(403, 'You are not authorized to approve this leave at this level.');
        }

        LeaveApprovalRecord::updateOrCreate(
            [
                'leave_id' => $leave->id,
                'approver_id' => $user->id,
                'sequence_number' => $approvalLevel,
            ],
            [
                'status' => 'approved',
                'decided_at' => now(),
            ]
        );

        $highestApprovalLevel = $leave->highestApprovalLevel();
        $isHighestLevelApproval = $highestApprovalLevel !== null && $approvalLevel === $highestApprovalLevel;
        $nextLevel = $leave->nextApprovalLevelAfter($approvalLevel);

        if ($isHighestLevelApproval || $nextLevel === null) {
            $balanceYear = $leave->start_date?->year ?? now()->year;
            $leave->loadMissing(['leaveType', 'employee.organisation']);

            $leave->update([
                'status' => LeaveStatusEnum::APPROVED,
                'approved_by' => $user->id,
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

            $field = match (LeaveTypeResolver::bucketFromLeaveType($leave->leaveType, $leave->type)) {
                'annual' => 'annual_used',
                'medical' => 'medical_used',
                'unpaid' => 'unpaid_used',
                default => null,
            };

            if ($field) {
                $value = $leave->leaveType?->deductionValue() ?? 1.0;
                $deduction = (float) $leave->duration_days * $value;

                if ($leave->is_half_day && $value === 1.0) {
                    $deduction = 0.5;
                }

                $balance->increment($field, $deduction);
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
