<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 13:06:21 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteFixedAddress;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderDeliveryAddress extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $addressFields = Arr::get($modelData, 'address');
        $address       = new Address($addressFields);

        data_set($modelData, 'address', $address);
        data_set($modelData, 'type', 'delivery');

        UpdateOrderFixedAddress::make()->action($order, $modelData);
        foreach ($order->deliveryNotes as $deliveryNote) {
            if ($this->canModifyDeliveryNoteAddress($deliveryNote)) {
                UpdateDeliveryNoteFixedAddress::make()->action($deliveryNote, $modelData);
            }
        }


        return $order;
    }

    public function canModifyDeliveryNoteAddress(DeliveryNote $deliveryNote): bool
    {
        if (in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED,
            DeliveryNoteStateEnum::CANCELLED,

        ])) {
            return false;
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKED && $deliveryNote->shipments()->count() > 0) {
            return false;
        }


        return true;
    }


    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress()],

        ];
    }

    public function action(Order $order, array $modelData): Order
    {
        $this->asAction = true;
        $this->order    = $order;

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
