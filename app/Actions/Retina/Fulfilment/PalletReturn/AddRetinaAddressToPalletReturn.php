<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\AddAddressToPalletReturn;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\RetinaPalletReturnResource;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

class AddRetinaAddressToPalletReturn extends RetinaAction
{
    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        return AddAddressToPalletReturn::run($palletReturn, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $request->route()->parameter('palletReturn')->fulfilmentCustomer->id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['required', 'exists:addresses,id'],
        ];
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisation($request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($palletReturn->fulfilmentCustomer, $modelData);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function jsonResponse(PalletReturn $palletReturn): RetinaPalletReturnResource
    {
        return new RetinaPalletReturnResource($palletReturn);
    }

    public function htmlResponse(): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return back();
    }

}
