<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\DeletePalletReturn;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\RedirectResponse;

class DeleteRetinaPalletReturn extends RetinaAction
{
    public function handle(PalletReturn $palletReturn, array $modelData = []): void
    {
        DeletePalletReturn::run($palletReturn, $modelData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.storage.pallet_returns.index');
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['sometimes', 'nullable']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        } elseif ($this->fulfilmentCustomer->id == $request->route()->parameter('palletReturn')->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function asController(Organisation $organisation, PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($this->fulfilmentCustomer, $modelData);
        $this->handle($palletReturn, $this->validatedData);
    }
}
