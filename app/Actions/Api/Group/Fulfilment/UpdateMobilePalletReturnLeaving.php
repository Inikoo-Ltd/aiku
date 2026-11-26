<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturn\AddAddressToPalletReturn;
use App\Actions\Fulfilment\PalletReturn\DeletePalletReturnAddress;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobilePalletReturnLeaving
{
    use AsController;
    use WithMobilePalletReturns;

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);

        $addressIds = IndexMobilePalletReturnAddresses::customerAddresses($palletReturn)->map(fn (Address $address) => $address->id)->all();
        $data       = Validator::make($request->all(), [
            'collection' => ['required', 'boolean'],
            'address_id' => ['exclude_if:collection,true', 'required', 'integer', Rule::in($addressIds)],
        ])->validate();

        if ($data['collection']) {
            abort_unless(self::canSetPalletReturnAsCollection($user, $palletReturn), 403, __('Only the fulfilment shop can change how a return leaves.'));
            if ($palletReturn->deliveryAddress) {
                DeletePalletReturnAddress::make()->handle($palletReturn);
            } else {
                $palletReturn->update(['is_collection' => true]);
            }

            return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('The customer collects this return.'));
        }

        abort_unless(self::canSetPalletReturnDeliveryAddress($user, $palletReturn), 403, __('Only the fulfilment shop can choose where a return is sent.'));
        AddAddressToPalletReturn::make()->action($palletReturn, ['delivery_address_id' => $data['address_id']]);

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('This return will be shipped.'));
    }
}
