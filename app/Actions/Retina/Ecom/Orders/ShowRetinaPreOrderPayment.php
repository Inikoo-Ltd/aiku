<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentApiPoint;
use App\Actions\Accounting\Traits\CalculatesPaymentWithBalance;
use App\Actions\Ordering\PreOrder\GetOrderAmountToPayNow;
use App\Actions\Ordering\PreOrder\GetPreOrderShowcase;
use App\Actions\Retina\GetRetinaPaymentMethods;
use App\Actions\RetinaAction;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The link in the balance request email: the customer pays what their pre-order still owes, by
 * card or from their account balance (HELP-3432).
 */
class ShowRetinaPreOrderPayment extends RetinaAction
{
    use CalculatesPaymentWithBalance;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('order')->customer_id == $this->customer->id;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order);
    }

    public function htmlResponse(Order $order): Response
    {
        $preOrder = $order->preOrder;
        abort_unless($preOrder, 404);

        $showcase       = GetPreOrderShowcase::run($preOrder);
        $paymentMethods = [];
        if ($showcase['can_pay_balance']) {
            $orderPaymentApiPoint = StoreOrderPaymentApiPoint::run($order);
            $paymentMethods       = array_values(array_filter(
                GetRetinaPaymentMethods::run($order, $orderPaymentApiPoint),
                fn ($paymentMethod) => ($paymentMethod['key'] ?? null) == 'credit_card'
            ));
        }

        return Inertia::render('Ecom/RetinaPreOrderPayment', [
            'title'          => __('Pay pre-order balance'),
            'pageHead'       => [
                'title' => $order->reference,
                'model' => __('Pre-order balance'),
                'icon'  => 'fal fa-hourglass-half',
            ],
            'order'          => OrderResource::make($order)->resolve(),
            'pre_order'      => $showcase,
            'paymentMethods' => $paymentMethods,
            'to_pay_data'    => $this->calculatePaymentWithBalance(GetOrderAmountToPayNow::run($order), $this->customer->balance),
            'currency_code'  => $order->currency->code,
            'routes'         => [
                'pay_with_balance' => [
                    'name'       => 'retina.models.order.pay_with_balance_after_submitted',
                    'parameters' => ['order' => $order->id],
                    'method'     => 'post',
                ],
                'order'            => [
                    'name'       => 'retina.ecom.orders.show',
                    'parameters' => ['order' => $order->slug],
                ],
            ],
        ]);
    }
}
