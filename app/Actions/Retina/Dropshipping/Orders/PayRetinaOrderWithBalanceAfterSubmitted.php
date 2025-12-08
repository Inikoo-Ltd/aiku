<?php

/*
 * Author: Vika Aqordi
 * Created on 27-11-2025-13h-55m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\CRM\Customer\PayOrderWithCustomerBalance;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PayRetinaOrderWithBalanceAfterSubmitted extends RetinaAction
{
    public function handle(Order $order): array
    {
        return PayOrderWithCustomerBalance::run($order);
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route()->parameter('order');
        if ($order->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $result = $this->handle($order);
        request()->session()->flash('notification', [
            'status' => $result['success'] ? 'success' : 'error',
            'title' => Arr::get($result, 'reason', ''),
            'description' => '',
        ]);
    }
}
