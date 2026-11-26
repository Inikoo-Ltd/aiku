<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePalletReturnAddresses
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @return Collection<int, Address>
     */
    public static function customerAddresses(PalletReturn $palletReturn): Collection
    {
        $customer = $palletReturn->fulfilmentCustomer->customer;

        return $customer->addresses
            ->push($customer->address, $customer->deliveryAddress)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);
        abort_unless(self::canSetPalletReturnDeliveryAddress($user, $palletReturn), 403, __('Only the fulfilment shop can choose where a return is sent.'));

        $customer = $palletReturn->fulfilmentCustomer->customer;

        return response()->json([
            'data' => self::customerAddresses($palletReturn)->map(fn (Address $address) => [
                'id'                  => $address->id,
                'lines'               => $this->addressLines($address),
                'is_default_delivery' => $address->id === $customer->delivery_address_id,
                'is_selected'         => !$palletReturn->is_collection && $address->id === $palletReturn->delivery_address_id,
            ])->all(),
        ]);
    }
}
