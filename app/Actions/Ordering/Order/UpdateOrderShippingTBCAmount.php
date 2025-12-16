<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class UpdateOrderShippingTBCAmount extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        $order->update(
            [
                'shipping_tbc_amount' => Arr::get($modelData, 'shipping_tbc_amount'),
                'shipping_amount' => Arr::get($modelData, 'shipping_tbc_amount'),
            ]
        );

        $order->refresh();
        CalculateOrderTotalAmounts::run($order, false);

        return $order;

    }

    public function rules(): array
    {
        return [
                'shipping_tbc_amount' => ['required', 'numeric', 'min:0'],
        ];

    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $order = UpdateOrderIsShippingTBC::run($this->order);
        if (!$order->is_shipping_tbc) {
            $validator->errors()->add('shipping_tbc_amount', 'Shipping amount change is not allowed for this order');
        }


    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
