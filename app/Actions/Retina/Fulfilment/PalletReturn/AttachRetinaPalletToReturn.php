<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-14h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\Pallet\AttachPalletToReturn;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

class AttachRetinaPalletToReturn extends RetinaAction
{
    public function handle(PalletReturn $palletReturn, Pallet $pallet): PalletReturn
    {
        return AttachPalletToReturn::run($palletReturn, $pallet);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $request->route()->parameter('palletReturn')->fulfilment_customer_id
            and $this->fulfilmentCustomer->id == $request->route()->parameter('pallet')->fulfilment_customer_id
        ) {
            return true;
        }

        return false;
    }

    public function asController(PalletReturn $palletReturn, Pallet $pallet, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($palletReturn, $pallet);
    }

    public function action(PalletReturn $palletReturn, Pallet $pallet)
    {
        $this->asAction = true;
        $this->initialisationActions($palletReturn->fulfilmentCustomer->customer, []);

        return $this->handle($palletReturn, $pallet);
    }



}
