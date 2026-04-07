<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaveApprover extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, LeaveApprover $leaveApprover, array $leaveApproverIds = []): bool
    {
        $organisationIds = $organisation->group->organisations()->pluck('id');

        if (!$organisationIds->contains($leaveApprover->organisation_id)) {
            abort(404);
        }

        $ids = collect($leaveApproverIds)
            ->push($leaveApprover->id)
            ->reject(fn ($id) => blank($id))
            ->map(fn ($id) => (int)$id)
            ->unique()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return false;
        }

        return LeaveApprover::query()
                ->whereIn('organisation_id', $organisationIds)
                ->whereIn('id', $ids)
                ->delete() > 0;
    }

    public function action(LeaveApprover $leaveApprover): bool
    {
        return $this->handle($leaveApprover->organisation, $leaveApprover);
    }

    public function asController(Organisation $organisation, LeaveApprover $leaveApprover, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $leaveApprover, Arr::wrap($request->input('leave_approver_ids', [])));
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Leave approver successfully deleted.'),
            ]);
    }
}
