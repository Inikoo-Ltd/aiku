<?php

/*
 * Author: Vika Aqordi
 * Created on 07-05-2026-16h-20m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Pallet\AttachPalletsToReturn;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

class AttachRetinaPalletsToReturn extends RetinaAction
{
    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        return AttachPalletsToReturn::run($palletReturn, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $request->route()->parameter('palletReturn')->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'pallets' => ['sometimes', 'array']
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisation($request);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
