<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\PalletDelivery\DeletePalletDelivery;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPalletDelivery extends RetinaAction
{
    public function handle(PalletDelivery $palletDelivery, array $modelData = []): void
    {
        DeletePalletDelivery::run($palletDelivery, $modelData);

    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('retina.fulfilment.storage.pallet_deliveries.index');
    }

    public function rules(): array
    {
        return [
            'delete_comment' => ['sometimes', 'nullable'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        } elseif ($this->fulfilmentCustomer->id == $request->route()->parameter('palletDelivery')->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function asController(Organisation $organisation, PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($palletDelivery, $this->validatedData);
    }
}
