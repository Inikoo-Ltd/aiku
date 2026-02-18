<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Auth;

class ApproveOvertimeRequest extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(OvertimeRequest $overtimeRequest): OvertimeRequest
    {

        $overtimeRequest->update([
            'status'                  => OvertimeRequestStatusEnum::APPROVED,
            'approved_at'             => now(),
            'approved_by_employee_id' => Auth::user()->employee->id ?? null,
        ]);

        return $overtimeRequest;
    }

    public function action(OvertimeRequest $overtimeRequest): OvertimeRequest
    {
        return $this->handle($overtimeRequest);
    }

    public function asController(Organisation $organisation, OvertimeRequest $overtimeRequest, ActionRequest $request): OvertimeRequest
    {
        $this->initialisation($organisation, $request);

        return $this->handle($overtimeRequest);
    }

    public function htmlResponse(OvertimeRequest $overtimeRequest): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Overtime request successfully approved.'),
        ]);

        return Redirect::back();
    }
}
