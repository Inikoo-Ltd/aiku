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
use App\Models\Ordering\Order;
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

        CalculateOrderTotalAmounts::run($order);

        return $order;
    }


    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
