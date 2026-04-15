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
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return DB::transaction(function () use ($leave, $user) {
            $leave = Leave::query()
                ->whereKey($leave->id)
                ->with(['approvalRecords', 'leaveType', 'employee.organisation'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($leave->status !== LeaveStatusEnum::PENDING) {
                abort(409, __('Only pending leave can be approved.'));
            }

            $isAllAcceptedApprover = LeaveApprover::query()
                ->where('organisation_id', $leave->organisation_id)
                ->where('user_id', $user->id)
                ->where('sequence_number', LeaveApprover::SEQUENCE_ALL_ACCEPTED)
                ->where('is_active', true)
                ->exists();

            if (!$leave->canBeApprovedBy($user)) {
                $this->isAuthorized = false;

                return $leave;
            }

            if ($isAllAcceptedApprover) {
                LeaveApprovalRecord::create([
                    'leave_id' => $leave->id,
                    'approver_id' => $user->id,
                    'sequence_number' => LeaveApprover::SEQUENCE_ALL_ACCEPTED,
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);
                $nextLevelApprovers = collect();
            } else {
                $currentLevel = $leave->currentApprovalLevel();

                $pendingRecord = LeaveApprovalRecord::query()
                    ->where('leave_id', $leave->id)
                    ->where('approver_id', $user->id)
                    ->where('sequence_number', $currentLevel)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$pendingRecord) {
                    $this->isAuthorized = false;

                    return $leave;
                }

                $pendingRecord->update([
                    'status' => 'approved',
                    'decided_at' => now(),
                ]);

                $nextLevelApprovers = LeaveApprover::query()
                    ->where('organisation_id', $leave->organisation_id)
                    ->where('sequence_number', $currentLevel + 1)
                    ->where('is_active', true)
                    ->get();
            }

            if ($nextLevelApprovers->isNotEmpty()) {
                foreach ($nextLevelApprovers as $approver) {
                    LeaveApprovalRecord::firstOrCreate([
                        'leave_id' => $leave->id,
                        'approver_id' => $approver->user_id,
                        'sequence_number' => $approver->sequence_number,
                    ], [
                        'status' => 'pending',
                    ]);
                }

                return $leave->refresh();
            }

            $leave->update([
                'status' => LeaveStatusEnum::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $balanceYear = $leave->start_date?->year ?? now()->year;
            $balance = $this->lockBalanceRow($leave->employee, $balanceYear);

            $field = match (LeaveTypeResolver::bucketFromLeaveType($leave->leaveType, $leave->type)) {
                'annual' => 'annual_used',
                'medical' => 'medical_used',
                'unpaid' => 'unpaid_used',
                default => null,
            };

            if ($field) {
                $deduction = $leave->is_half_day ? 0.5 : (float)$leave->duration_days;
                $balance->increment($field, $deduction);
            }

            return $leave->refresh();
        }, 3);
    }

    private function lockBalanceRow(\App\Models\HumanResources\Employee $employee, int $year): EmployeeLeaveBalance
    {
        $balance = EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        try {
            return EmployeeLeaveBalance::create([
                'employee_id' => $employee->id,
                'year' => $year,
                'annual_days' => $employee->organisation->getDefaultAnnualLeaveDays(),
                'annual_used' => 0,
                'medical_days' => 0,
                'medical_used' => 0,
                'unpaid_days' => 0,
                'unpaid_used' => 0,
            ]);
        } catch (QueryException) {
            return EmployeeLeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }
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
