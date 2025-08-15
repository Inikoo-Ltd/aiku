<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Address\FixedAddressGarbageCollection;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\Ordering\Order;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderFixedAddress extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $type = Arr::get($modelData, 'type');

        if ($type == 'billing') {
            $oldAddress = $order->billingAddress;
        } else {
            $oldAddress = $order->deliveryAddress;
        }


        if ($oldAddress && $oldAddress->checksum == $modelData['address']->getChecksum()) {
            return $order;
        }

        if ($oldAddress) {
            $order->fixedAddresses()->detach($oldAddress->id);
        }

        $address = $this->createFixedAddress($order, $modelData['address'], 'Ordering', $type, $type == 'billing' ? 'billing_address_id' : 'delivery_address_id');

        $order->updateQuietly(
            [
                $type . '_country_id' => $address->country_id,
            ]
        );

        if ($oldAddress) {
            FixedAddressGarbageCollection::dispatch($oldAddress)->delay($this->hydratorsDelay);
        }




        return $order;
    }

    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress()],
            'type'    => ['required', Rule::in(['billing', 'delivery'])],

        ];
    }

    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $audit = true): Order
    {
        if (!$audit) {
            Order::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->order          = $order;

        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
