<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 13-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturnDeliveryAddress;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPalletReturnDeliveryAddress extends RetinaAction
{
    public function handle(PalletReturn $palletReturn, array $modelData): void
    {
        UpdatePalletReturnDeliveryAddress::run($palletReturn, $modelData);
    }

    public function rules(): array
    {
        return [
            'address' => ['sometimes'],
        ];
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

    public function asController(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($palletReturn->fulfilmentCustomer, $modelData);

        $this->handle($palletReturn, $this->validatedData);
    }

    public function htmlResponse(): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return back();
    }
}
