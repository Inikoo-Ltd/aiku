<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 19:55:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\FulfilmentTransaction\StoreFulfilmentTransaction;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaFulfilmentTransaction extends RetinaAction
{
    protected Organisation $organisation;

    public function handle(PalletDelivery|PalletReturn $parent, array $modelData): FulfilmentTransaction
    {
        return StoreFulfilmentTransaction::run($parent, $modelData);
    }

    public function rules(): array
    {
        return [
            'is_auto_assign' => ['sometimes', 'boolean'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'historic_asset_id' => [
                'required',
                Rule::Exists('historic_assets', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
        ];
    }

    public function fromRetinaInPalletDelivery(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function fromRetinaInPalletReturn(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn|PalletDelivery $parent, array $modelData): FulfilmentTransaction
    {
        $this->asAction = true;
        $this->initialisationFulfilmentActions($parent->fulfilmentCustomer, $modelData);

        return $this->handle($parent, $this->validatedData);
    }
}
