<?php

namespace App\Actions\HumanResources\Concurrency;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveConcurrencyRule;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaveConcurrencyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(LeaveConcurrencyRule $leaveConcurrencyRule): bool
    {
        return (bool) $leaveConcurrencyRule->delete();
    }

    public function action(LeaveConcurrencyRule $leaveConcurrencyRule): bool
    {
        return $this->handle($leaveConcurrencyRule);
    }

    public function asController(Organisation $organisation, LeaveConcurrencyRule $leaveConcurrencyRule, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($leaveConcurrencyRule);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave concurrency rule successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
