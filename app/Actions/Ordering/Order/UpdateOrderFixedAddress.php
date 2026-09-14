<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Address\FixedAddressGarbageCollection;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
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

        if ($type == 'delivery') {
            SetOrderDeliveryCountry::run($order, $address);
        } else {
            $order->updateQuietly(['billing_country_id' => $address->country_id]);
        }

        if ($oldAddress) {
            FixedAddressGarbageCollection::dispatch($oldAddress->id)->delay($this->hydratorsDelay);
        }

        $this->releaseIfHeldForAMissingAddress($order);

        return $order;
    }

    /**
     * An order whose customer had no address never reached the warehouse (HELP-3102). Now that an address
     * has been put on it, it is offered to the warehouse again: SendOrderToWarehouse holds it once more by
     * itself if what arrived is still not enough, so there is nothing to check twice here.
     */
    private function releaseIfHeldForAMissingAddress(Order $order): void
    {
        $order->refresh();

        /** Only an order aiku itself held; an Aurora fetch changing an address must not push anything to the warehouse */
        if ($order->source_id || !str_contains((string)$order->private_warehouse_note, SendOrderToWarehouse::HELD_MARKER)) {
            return;
        }

        if ($order->state != OrderStateEnum::SUBMITTED) {
            return;
        }

        if ($order->pay_status != OrderPayStatusEnum::PAID && $order->to_be_paid_by != OrderToBePaidByEnum::CASH_ON_DELIVERY) {
            return;
        }

        if ($order->deliveryNotes()->exists()) {
            return;
        }

        SendOrderToWarehouse::make()->action($order, []);
    }

    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress(requireFullAddress: !$this->asAction)],
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
