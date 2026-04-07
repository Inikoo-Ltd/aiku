<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprovalRecord;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RejectLeave extends OrgAction
{
    public function handle(Leave $leave, string $rejectionReason): Leave
    {
        $user = Auth::user();

        if (!$leave->canBeApprovedBy($user)) {
            abort(403, 'You are not authorized to reject this leave at this level.');
        }

        $currentLevel = LeaveApprover::byOrganisation($leave->organisation)
            ->active()
            ->where('user_id', $user->id)
            ->where('sequence_number', LeaveApprover::SEQUENCE_ALL_ACCEPTED)
            ->exists()
            ? LeaveApprover::SEQUENCE_ALL_ACCEPTED
            : $leave->currentApprovalLevel();

        LeaveApprovalRecord::create([
            'leave_id' => $leave->id,
            'approver_id' => $user->id,
            'sequence_number' => $currentLevel,
            'status' => 'rejected',
            'comments' => $rejectionReason,
            'decided_at' => now(),
        ]);

        $leave->update([
            'status' => LeaveStatusEnum::REJECTED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

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
