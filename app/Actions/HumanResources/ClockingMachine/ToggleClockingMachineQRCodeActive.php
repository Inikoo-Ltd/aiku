<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 14:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachineQRCode;
use Lorisleiva\Actions\ActionRequest;

class ToggleClockingMachineQRCodeActive extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * Flip the QR code between active and inactive.
     *
     * Deactivating stops the code validating on scan while keeping the row, so clockings already
     * linked to it and its usage counters survive.
     */
    public function handle(ClockingMachineQRCode $clockingMachineQRCode): ClockingMachineQRCode
    {
        return UpdateClockingMachineQRCode::run($clockingMachineQRCode, [
            'active' => !$clockingMachineQRCode->active,
        ]);
    }

    public function asController(ClockingMachineQRCode $clockingMachineQRCode, ActionRequest $request): ClockingMachineQRCode
    {
        $this->initialisation($clockingMachineQRCode->clockingMachine->organisation, $request);

        return $this->handle($clockingMachineQRCode);
    }

    public function htmlResponse(ClockingMachineQRCode $clockingMachineQRCode): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => $clockingMachineQRCode->active
                ? __('QR code activated.')
                : __('QR code deactivated.'),
        ]);
    }
}
