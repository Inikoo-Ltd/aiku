<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 13:06:21 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteFixedAddress;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Ordering\Order\UI\AuditOrderCustom;
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

    public function handle(Order $order, array $modelData, $auditOnOrder = false): Order
    {
        $addressFields = Arr::get($modelData, 'address');
        $address       = new Address($addressFields);

        data_set($modelData, 'address', $address);
        data_set($modelData, 'type', 'delivery');

        $oldAddressData = $this->getAddressCustomized($order->deliveryAddress->toArray());

        $order = UpdateOrderFixedAddress::make()->action($order, $modelData);
        $order = CalculateOrderShipping::run($order);
        
        // Moved outside for Logging
        $addressData = $this->getAddressCustomized($order->deliveryAddress->toArray());

        if ($auditOnOrder) {
            AuditOrderCustom::run($order, $this->prefixKeysLogging($oldAddressData), Arr::except($this->prefixKeysLogging($addressData), ['shipping_checksum', 'shipping_country_id']));
        }

        if (Arr::get($modelData, 'update_parent')) {
            if ($order->customer_client_id) {
                UpdateCustomerClient::make()->action(
                    $order->customerClient,
                    [
                        'address' => $addressData
                    ]
                );
            } else {
                UpdateCustomer::make()->action(
                    $order->customer,
                    [
                        'delivery_address' => $addressData
                    ]
                );
            }
        }


        foreach ($order->deliveryNotes as $deliveryNote) {
            if ($this->canModifyDeliveryNoteAddress($deliveryNote)) {
                UpdateDeliveryNoteFixedAddress::make()->action($deliveryNote, $modelData);
            }
        }


        return $order;
    }

    public function getAddressCustomized(array $address): array
    {
        return Arr::only(
                $address,
                [
                    'address_line_1',
                    'address_line_2',
                    'sorting_code',
                    'postal_code',
                    'locality',
                    'dependent_locality',
                    'administrative_area',
                    'country_code',
                    'country_id',
                    'checksum'
                ]
            );
    }

    public function prefixKeysLogging(array $address): array
    {
        $prefixed = [];

        foreach ($address as $key => $value) {
            $prefixed['shipping_' . $key] = $value;
        }

        return $prefixed;
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
            'update_parent' => ['sometimes', 'boolean'],
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

        return $this->handle($order, $this->validatedData, true);
    }
}
