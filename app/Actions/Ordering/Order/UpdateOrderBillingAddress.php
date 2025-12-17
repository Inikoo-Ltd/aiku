<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 13:06:21 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Retina\Dropshipping\Orders\UpdateCustomerOrderTaxCategory;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;

class UpdateOrderBillingAddress extends OrgAction
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
        data_set($modelData, 'type', 'billing');

        $order = UpdateOrderFixedAddress::make()->action($order, $modelData);

        $order = UpdateCustomerOrderTaxCategory::run($order);
        CalculateOrderTotalAmounts::run($order, true);

        $order->refresh();

        return $order;
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


}
