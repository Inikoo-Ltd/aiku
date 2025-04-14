<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\UpdatePalletReturn;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPalletReturn extends RetinaAction
{
    private PalletReturn $palletReturn;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        return UpdatePalletReturn::run($palletReturn, $modelData);
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
            'customer_reference'        => ['sometimes', 'nullable', 'string', Rule::unique('pallet_returns', 'customer_reference')
                ->ignore($this->palletReturn->id)],
            'reference'      => ['sometimes', 'string', 'max:255'],
            'estimated_delivery_date'   => ['sometimes', 'date'],
            'public_notes'   => ['sometimes', 'nullable', 'string', 'max:4000'],
            'customer_notes'   => ['sometimes', 'nullable', 'string', 'max:4000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'collection_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function asController(Organisation $organisation, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->palletReturn = $palletReturn;
        $this->initialisation($request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $this->asAction = true;
        $this->palletReturn = $palletReturn;
        $this->initialisationFulfilmentActions($palletReturn->fulfilmentCustomer, $modelData);
        return $this->handle($palletReturn, $modelData);
    }

    public function htmlResponse()
    {
        return back();
    }
}
