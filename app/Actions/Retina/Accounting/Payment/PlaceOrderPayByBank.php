<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-15h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
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

class PlaceOrderPayByBank extends RetinaAction
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
        if(!$order){
            abort(404);
        }

        SettleRetinaOrderWithBalance::run($order);
        $customer->refresh();

        return $this->placeOrderByPaymentMethod($customer, OrderToBePaidByEnum::BANK);
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
