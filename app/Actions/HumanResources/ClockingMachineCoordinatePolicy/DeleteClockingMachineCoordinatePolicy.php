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

    public function asController(ClockingMachineCoordinatePolicy $clockingMachineCoordinatePolicy, ActionRequest $request): bool
    {
        $this->initialisation($clockingMachineCoordinatePolicy->organisation, $request);

        return $this->handle($clockingMachineCoordinatePolicy);
    }
}
