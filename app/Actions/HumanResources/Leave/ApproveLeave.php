<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use App\Services\HumanResources\LeaveTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class ApproveLeave extends OrgAction
{
    protected bool $isAuthorized = true;

    /**
     * @throws Throwable
     */
    public function handle(Leave $leave): Leave
    {
        $user = Auth::user();

        if (!$leave->canBeApprovedBy($user)) {
            $this->isAuthorized = false;

            return $leave;
        }

        $isAllAcceptedApprover = LeaveApprover::byOrganisation($leave->organisation)
            ->active()
            ->where('user_id', $user->id)
            ->where('sequence_number', LeaveApprover::SEQUENCE_ALL_ACCEPTED)
            ->exists();

        DB::transaction(function () use ($leave, $user, $isAllAcceptedApprover) {
            $currentLevel = $isAllAcceptedApprover
                ? LeaveApprover::SEQUENCE_ALL_ACCEPTED
                : $leave->currentApprovalLevel();

            LeaveApprovalRecord::create([
                'leave_id' => $leave->id,
                'approver_id' => $user->id,
                'sequence_number' => $currentLevel,
                'status' => 'approved',
                'decided_at' => now(),
            ]);

            $nextLevelApprovers = $isAllAcceptedApprover
                ? collect()
                : LeaveApprover::byOrganisation($leave->organisation)
                    ->bySequence($currentLevel + 1)
                    ->active()
                    ->get();

            if ($nextLevelApprovers->isEmpty()) {
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
                        'year' => $balanceYear,
                    ],
                    [
                        'annual_days' => $leave->employee->organisation->getDefaultAnnualLeaveDays(),
                        'annual_used' => 0,
                        'medical_days' => 0,
                        'medical_used' => 0,
                        'unpaid_days' => 0,
                        'unpaid_used' => 0,
                    ]
                );

                $field = match (LeaveTypeResolver::bucketFromLeaveType($leave->leaveType, $leave->type)) {
                    'annual' => 'annual_used',
                    'medical' => 'medical_used',
                    'unpaid' => 'unpaid_used',
                    default => null,
                };

                if ($field) {
                    $isHalfDay = $leave->is_half_day
                        || in_array((string)$leave->type, ['halfday-morning', 'halfday-afternoon'], true);

                    $deduction = $isHalfDay ? 0.5 : (float)$leave->duration_days;
                    $balance->increment($field, $deduction);
                }
            } else {
                foreach ($nextLevelApprovers as $approver) {
                    LeaveApprovalRecord::create([
                        'leave_id' => $leave->id,
                        'approver_id' => $approver->user_id,
                        'sequence_number' => $currentLevel + 1,
                        'status' => 'pending',
                    ]);
                }
            }
        });

        return $leave;
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leave);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        if (!$this->isAuthorized) {
            return Redirect::back()
                ->with('notification', [
                    'status' => 'error',
                    'title' => __('Unauthorized'),
                    'description' => __('Sorry, You are not listed on the Leave Approval. '),
                ]);
        }

        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave request approved.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource|\Illuminate\Http\JsonResponse
    {
        if (!$this->isAuthorized) {
            return response()->json([
                'message' => __('You are not authorized to approve this leave at this level.'),
            ], 403);
        }

        return LeaveResource::make($leave);
    }
}
