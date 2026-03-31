<?php

namespace App\Actions\HumanResources\ClockingMachineCoordinatePolicyRule;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachineCoordinatePolicyRule;
use Lorisleiva\Actions\ActionRequest;

class DeleteClockingMachineCoordinatePolicyRule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineCoordinatePolicyRule $ruleModel): bool
    {
        return (bool) $ruleModel->delete();
    }

    public function asController(ClockingMachineCoordinatePolicyRule $clockingMachineCoordinatePolicyRule, ActionRequest $request): bool
    {
        $this->initialisation($clockingMachineCoordinatePolicyRule->policy->organisation, $request);

        return $this->handle($clockingMachineCoordinatePolicyRule);
    }
}
