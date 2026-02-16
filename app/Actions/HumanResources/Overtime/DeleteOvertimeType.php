<?php


namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\OvertimeType;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteOvertimeType extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(OvertimeType $overtimeType): bool
    {
        return (bool) $overtimeType->delete();
    }

    public function action(OvertimeType $overtimeType): bool
    {
        return $this->handle($overtimeType);
    }

    public function asController(Organisation $organisation, OvertimeType $overtimeType, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($overtimeType);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('success', __('Overtime type deleted successfully.'));
    }
}
