<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveType;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaveType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(LeaveType $leaveType): bool
    {
        return (bool) $leaveType->delete();
    }

    public function action(LeaveType $leaveType): bool
    {
        return $this->handle($leaveType);
    }

    public function asController(Organisation $organisation, LeaveType $leaveType, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveType);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave type successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
