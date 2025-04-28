<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Actions\Fulfilment\PalletReturn\Search\PalletReturnRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AddAddressToPalletReturn extends OrgAction
{
    use WithActionUpdate;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn
    {
        $palletReturn->update([
            'delivery_address_id' => Arr::pull($modelData, 'delivery_address_id'),
            'is_collection'       => false
        ]);
        PalletReturnRecordSearch::dispatch($palletReturn);

        return $palletReturn;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            // TODO: Raul please do the permission for the web user
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'delivery_address_id' => ['required', 'exists:addresses,id'],
        ];
    }


    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $request);

        return $this->handle($palletReturn, $this->validatedData);
    }

    public function action(PalletReturn $palletReturn, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PalletReturn
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromFulfilment($palletReturn->fulfilment, $modelData);


        return $this->handle($palletReturn, $this->validatedData);
    }

    public function jsonResponse(PalletReturn $palletReturn): PalletReturnResource
    {
        return new PalletReturnResource($palletReturn);
    }
}
