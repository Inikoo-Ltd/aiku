<?php
/*
 * author Arya Permana - Kirin
 * created on 27-03-2025-13h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class SwitchPalletReturnDeliveryAddress extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        if (isset($modelData['delivery_address_id'])) {
            $palletReturn->delivery_address_id = $modelData['delivery_address_id'];
            $palletReturn->save();
        }
        return $palletReturn;
    }

    public function rules(): array
    {
        $rules = [
            'delivery_address_id'         => ['sometimes', 'nullable', 'exists:addresses,id'],
        ];

        return $rules;
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
