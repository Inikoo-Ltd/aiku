<?php

namespace App\Actions\HumanResources\RestrictedPeriods;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\RestrictedPeriod;
use App\Models\HumanResources\RestrictedPeriodTarget;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRestrictedPeriodTarget extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(RestrictedPeriodTarget $restrictedPeriodTarget): bool
    {
        return (bool) $restrictedPeriodTarget->delete();
    }

    public function action(RestrictedPeriodTarget $restrictedPeriodTarget): bool
    {
        return $this->handle($restrictedPeriodTarget);
    }

    public function asController(Organisation $organisation, RestrictedPeriod $restrictedPeriod, RestrictedPeriodTarget $restrictedPeriodTarget, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($restrictedPeriodTarget);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Target successfully removed from restricted period.'),
        ]);

        return Redirect::back();
    }
}
