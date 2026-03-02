<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteOvertimeRequest extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(OvertimeRequest $overtimeRequest): bool
    {
        return (bool) $overtimeRequest->delete();
    }

    public function action(OvertimeRequest $overtimeRequest): bool
    {
        return $this->handle($overtimeRequest);
    }

    public function asController(Organisation $organisation, OvertimeRequest $overtimeRequest, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($overtimeRequest);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Overtime request successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
