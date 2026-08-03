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

class RegenerateClockingMachineQRCodeHash extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * Issue a new hash for an existing QR code.
     *
     * The row is kept so clockings already linked to it and its usage counters survive, but every
     * printed or displayed copy of the previous hash stops validating immediately.
     */
    public function handle(ClockingMachineQRCode $clockingMachineQRCode): ClockingMachineQRCode
    {
        $clockingMachineQRCode->update([
            'hash' => StoreClockingMachineQRCode::freshHash(),
        ]);

        return $clockingMachineQRCode->refresh();
    }

    public function asController(ClockingMachineQRCode $clockingMachineQRCode, ActionRequest $request): ClockingMachineQRCode
    {
        $this->initialisation($clockingMachineQRCode->clockingMachine->organisation, $request);

        return $this->handle($clockingMachineQRCode);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('A new QR code hash has been generated.'),
        ]);
    }
}
