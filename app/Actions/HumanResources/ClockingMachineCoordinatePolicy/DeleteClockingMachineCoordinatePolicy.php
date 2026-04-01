<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicy;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachineCoordinatePolicy;
use Lorisleiva\Actions\ActionRequest;

class DeleteClockingMachineCoordinatePolicy extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineCoordinatePolicy $policy): bool
    {
        return (bool) $policy->delete();
    }

    public function asController(ClockingMachineCoordinatePolicy $policy, ActionRequest $request): bool
    {
        $this->initialisation($policy->organisation, $request);

        return $this->handle($policy);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Clocking policy successfully deleted.'),
        ]);
    }
}
