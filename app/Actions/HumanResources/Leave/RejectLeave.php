<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\SysAdmin\Organisation;
use App\Notifications\LeaveDecisionNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RejectLeave extends OrgAction
{
    public function handle(Leave $leave, string $rejectionReason): Leave
    {
        ApproveLeave::ensurePending($leave);

        $user = Auth::user();

        if (!$user) {
            abort(403, 'You are not authorized to reject this leave at this level.');
        }

        $approvalLevel = $leave->approvalLevelForUser($user);
        if ($approvalLevel === null) {
            abort(403, 'You are not authorized to reject this leave at this level.');
        }

        LeaveApprovalRecord::updateOrCreate(
            [
                'leave_id' => $leave->id,
                'approver_id' => $user->id,
                'sequence_number' => $approvalLevel,
            ],
            [
                'status' => 'rejected',
                'comments' => $rejectionReason,
                'decided_at' => now(),
            ]
        );

        $leave->update([
            'status' => LeaveStatusEnum::REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        LeaveDecisionNotification::sendToEmployee($leave);

        return $leave;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        abort_unless($leave->organisation_id === $organisation->id, 404);
        ApproveLeave::ensurePending($leave);
        $this->initialisation($organisation, $request);

        return $this->handle($leave, $this->validatedData['rejection_reason']);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave request rejected.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
