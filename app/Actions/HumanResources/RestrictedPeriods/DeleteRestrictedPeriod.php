<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRestrictedPeriod extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(RestrictedPeriod $restrictedPeriod): bool
    {
        return (bool) $restrictedPeriod->delete();
    }

    public function action(RestrictedPeriod $restrictedPeriod): bool
    {
        return $this->handle($restrictedPeriod);
    }

    public function asController(Organisation $organisation, RestrictedPeriod $restrictedPeriod, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($restrictedPeriod);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Restricted period successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
