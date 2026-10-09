<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\SysAdmin\Organisation;
use App\Notifications\LeaveApprovedNotification;
use App\Services\HumanResources\LeaveTypeResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ApproveLeave extends OrgAction
{
    private Leave $leave;

    /**
     * @param array{cover_employee_id?: int|null} $modelData
     */
    public function handle(Leave $leave, array $modelData = []): Leave
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'You are not authorized to approve this leave at this level.');
        }

        $approvalLevel = $leave->approvalLevelForUser($user);
        if ($approvalLevel === null) {
            abort(403, 'You are not authorized to approve this leave at this level.');
        }

        if (array_key_exists('cover_employee_id', $modelData)) {
            UpdateLeaveCover::make()->handle($leave, Arr::only($modelData, ['cover_employee_id']));
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
            $leave->loadMissing(['leaveType', 'employee.organisation']);

            $leave->update([
                'status' => LeaveStatusEnum::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $balance = $this->applyBalanceDeduction($leave);

            UpdateLeaveCover::make()->startCover($leave);

            if ($leave->organisation->email) {
                Notification::route('mail', $leave->organisation->email)
                    ->notify(new LeaveApprovedNotification($leave, $balance));
            }
        }

        return $leave;
    }

    public function applyBalanceDeduction(Leave $leave): EmployeeLeaveBalance
    {
        $leave->loadMissing('leaveType');
        $startDate = $leave->start_date ?? now();

        $balance = EmployeeLeaveBalance::where('employee_id', $leave->employee_id)
            ->where(function ($q) use ($startDate) {
                $q->where('period_start', '<=', $startDate->toDateString())
                  ->where(function ($q2) use ($startDate) {
                      $q2->whereNull('period_end')
                         ->orWhere('period_end', '>=', $startDate->toDateString());
                  });
            })
            ->whereNotNull('employee_contract_id')
            ->first();

        if (!$balance) {
            $balance = EmployeeLeaveBalance::firstOrCreate(
                ['employee_id' => $leave->employee_id, 'employee_contract_id' => null],
                [
                    'annual_used'  => 0,
                    'medical_used' => 0,
                    'unpaid_used'  => 0,
                ]
            );
        }

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

        return $balance;
    }

    public function rules(): array
    {
        return UpdateLeaveCover::coverRules($this->organisation, $this->leave->employee_id);
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('cover_employee_id') && UpdateLeaveCover::isOnLeaveDuring((int) $this->get('cover_employee_id'), $this->leave->start_date, $this->leave->end_date)) {
            $validator->errors()->add('cover_employee_id', __('This colleague is on leave during this period.'));
        }
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        abort_unless($leave->organisation_id === $organisation->id, 404);
        $this->leave = $leave;
        $this->initialisation($organisation, $request);

        return $this->handle($leave, $this->validatedData);
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
