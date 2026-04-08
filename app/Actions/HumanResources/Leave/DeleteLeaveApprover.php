<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaveApprover extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(LeaveApprover $leaveApprover): bool
    {
        return (bool) $leaveApprover->delete();
    }

    public function action(LeaveApprover $leaveApprover): bool
    {
        return $this->handle($leaveApprover);
    }

    public function asController(Organisation $organisation, LeaveApprover $leaveApprover, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveApprover);
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
