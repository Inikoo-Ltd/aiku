<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Country;
use App\Notifications\Work\WorkNotifier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdatePalletReturnDeliveryAddress extends OrgAction
{
    public function handle(PalletReturn $palletReturn, array $modelData): void
    {
        $addressData = Arr::get($modelData, 'address');
        if ($addressData) {
            $countryCode = Country::find($addressData['country_id'])->code;
            data_set($addressData, 'country_code', $countryCode);
            unset($addressData['id']);
            unset($addressData['label']);
            unset($addressData['can_edit']);
            unset($addressData['can_delete']);
            UpdateAddress::run($palletReturn->deliveryAddress, $addressData);
            WorkNotifier::modified($palletReturn, 'address');
        }
    }

    public function rules(): array
    {
        return [
            'address'             => ['sometimes'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof FulfilmentCustomer) {
            return $request->user()->authTo("crm.{$this->shop->id}.edit");
        } else {
            return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }
        return false;
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->scope = $palletReturn->customer;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);
        $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);
        $this->handle($palletReturn, $this->validatedData);
    }
}
