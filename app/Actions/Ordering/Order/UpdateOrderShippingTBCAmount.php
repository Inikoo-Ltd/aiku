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
use App\Enums\Ordering\Order\OrderStateEnum;
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
        CalculateOrderTotalAmounts::run(order: $order, forceRecalculate: true);

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
        if (in_array($this->order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED
        ])) {
            $validator->errors()->add('shipping_tbc_amount', __('Shipping can not be changed once order is dispatched or finalised.'));

            return;
        }

        $order = UpdateOrderIsShippingTBC::run($this->order);
        if (!$order->is_shipping_tbc) {
            $validator->errors()->add('shipping_tbc_amount', 'Shipping amount change is not allowed for this order');
        }


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
