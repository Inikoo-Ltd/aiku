<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\PreOrder\CancelPreOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use App\Models\Ordering\PreOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CancelRetinaPreOrder extends RetinaAction
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): PreOrder
    {
        $preOrder = $order->preOrder;
        if (!$preOrder) {
            throw ValidationException::withMessages(['order' => __('This order is not a pre-order.')]);
        }

        return CancelPreOrder::run($preOrder, CancelPreOrder::make()->customerReason($preOrder));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('order')->customer_id == $this->customer->id;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): PreOrder
    {
        $this->initialisation($request);

        return $this->handle($order);
    }
}
