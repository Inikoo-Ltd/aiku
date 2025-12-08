<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\DeletePallet;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\RetinaPalletResource;
use App\Models\Fulfilment\Pallet;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPallet extends RetinaAction
{
    private Pallet $pallet;

    public function handle(Pallet $pallet): Pallet
    {
        return DeletePallet::run($pallet);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $this->pallet->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisation($request);

        return $this->handle($pallet);
    }

    public function action(Pallet $pallet): Pallet
    {
        $this->asAction = true;
        $this->pallet = $pallet;
        $this->initialisationFulfilmentActions($pallet->fulfilmentCustomer, []);

        return $this->handle($pallet);
    }

    public function jsonResponse(Pallet $pallet): RetinaPalletResource
    {
        return new RetinaPalletResource($pallet);
    }

    public function htmlResponse()
    {
        return back();
    }
}
