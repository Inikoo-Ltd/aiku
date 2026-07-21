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

class UpdateClockingMachineQRCode extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachineQRCode $clockingMachineQRCode, array $modelData): ClockingMachineQRCode
    {
        if (array_key_exists('active', $modelData)) {
            $modelData['deactivated_at'] = $modelData['active'] ? null : now();
        }

        $clockingMachineQRCode->update($modelData);

        return $clockingMachineQRCode->refresh();
    }

    public function rules(): array
    {
        return [
            'label'  => ['sometimes', 'nullable', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ClockingMachineQRCode $clockingMachineQRCode, ActionRequest $request): ClockingMachineQRCode
    {
        $this->initialisation($clockingMachineQRCode->clockingMachine->organisation, $request);

        return $this->handle($clockingMachineQRCode, $this->validatedData);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('QR code successfully updated.'),
        ]);
    }
}
