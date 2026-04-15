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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RejectLeave extends OrgAction
{
    protected bool $isAuthorized = true;

    public function handle(Leave $leave, string $rejectionReason): Leave
    {
        $user = Auth::user();

        return DB::transaction(function () use ($leave, $user, $rejectionReason) {
            $leave = Leave::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();

            if ($leave->status !== LeaveStatusEnum::PENDING) {
                abort(409, __('Only pending leave can be rejected.'));
            }

            if (!$leave->canBeApprovedBy($user)) {
                $this->isAuthorized = false;

                return $leave;
            }

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

            return $leave->refresh();
        }, 3);
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
        if (!$this->isAuthorized) {
            return Redirect::back()
                ->with('notification', [
                    'status' => 'error',
                    'title' => __('Unauthorized'),
                    'description' => __('You are not authorized to reject this leave at this level.'),
                ]);
        }

        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave request rejected.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource|\Illuminate\Http\JsonResponse
    {
        if (!$this->isAuthorized) {
            return response()->json([
                'message' => __('You are not authorized to reject this leave at this level.'),
            ], 403);
        }

        return LeaveResource::make($leave);
    }
}
