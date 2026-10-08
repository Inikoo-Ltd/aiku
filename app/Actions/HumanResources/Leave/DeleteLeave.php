<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeave extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Leave $leave): bool
    {
        return (bool) $leave->delete();
    }

    public function action(Leave $leave): bool
    {
        return $this->handle($leave);
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leave);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave request successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
