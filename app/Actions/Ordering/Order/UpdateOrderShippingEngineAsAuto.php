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
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderShippingEngineAsAuto extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;

    private Order $order;

    public function handle(Order $order): Order
    {
        $order->update(
            [
                'shipping_engine' => OrderShippingEngineEnum::AUTO,
            ]
        );

        CalculateOrderTotalAmounts::run(order: $order, forceRecalculate: true);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if (in_array($this->order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED
        ])) {
            $validator->errors()->add('message', __('Shipping can not be changed once order is dispatched or finalised.'));
        }
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
