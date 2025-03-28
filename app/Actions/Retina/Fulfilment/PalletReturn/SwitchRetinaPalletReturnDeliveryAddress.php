<?php
/*
 * author Arya Permana - Kirin
 * created on 27-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\SwitchPalletReturnDeliveryAddress;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class SwitchRetinaPalletReturnDeliveryAddress extends RetinaAction
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn, array $modelData): void
    {
        SwitchPalletReturnDeliveryAddress::run($palletReturn, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'delivery_address_id'         => ['sometimes', 'nullable', 'exists:addresses,id'],
        ];

        return $rules;
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
