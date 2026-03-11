<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\HumanResources\LeaveConcurrencyTarget;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaveConcurrencyTarget extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(LeaveConcurrencyTarget $leaveConcurrencyTarget): bool
    {
        return (bool) $leaveConcurrencyTarget->delete();
    }

    public function action(LeaveConcurrencyTarget $leaveConcurrencyTarget): bool
    {
        return $this->handle($leaveConcurrencyTarget);
    }

    public function asController(Organisation $organisation, LeaveConcurrencyRule $leaveConcurrencyRule, LeaveConcurrencyTarget $leaveConcurrencyTarget, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveConcurrencyTarget);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Target successfully removed from leave concurrency rule.'),
        ]);

        return Redirect::back();
    }
}
