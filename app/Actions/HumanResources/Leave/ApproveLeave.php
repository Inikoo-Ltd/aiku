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
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ApproveLeave extends OrgAction
{
    private Leave $leave;

    /**
     * @param array{cover_employee_id?: int|null} $modelData
     */
    public function handle(Leave $leave, array $modelData = []): Leave
    {
        self::ensurePending($leave);

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

    public static function ensurePending(Leave $leave): void
    {
        $message = match ($leave->status) {
            LeaveStatusEnum::APPROVED => __('This leave request has already been approved.'),
            LeaveStatusEnum::REJECTED => __('This leave request has already been rejected.'),
            default                   => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    public function applyBalanceDeduction(Leave $leave): EmployeeLeaveBalance
    {
        return RecalculateEmployeeLeaveBalance::make()->forLeave($leave);
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
        self::ensurePending($leave);
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
