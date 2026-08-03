<?php

/*
 * Author Louis Perez
 * Created on 28-07-2026-09h-37m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class RemoveOrderDiscount extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        return UpdateOrderDiscretionaryDiscount::make()->action($order, [
            'discretionary_offer'   => 0,
            'discretionary_label'   => 'Discount Removal',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }

    public function htmlResponse(Order $order): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Order discount removed successfully for all items.'),
        ]);
    }
}
