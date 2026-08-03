<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 22:13:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\WithBasketStateWarning;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class PlaceOrderPayByCashOnDelivery extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;
    use WithPlaceOrderByPaymentMethod;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): array
    {
        $order = Order::find($customer->current_order_in_basket_id);
        if (!$order) {
            abort(404);
        }

        SettleRetinaOrderWithBalance::run($order);
        $customer->refresh();
        return $this->placeOrderByPaymentMethod($customer, OrderToBePaidByEnum::CASH_ON_DELIVERY);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);
        return $this->handle($this->customer);
    }

}
