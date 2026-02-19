<?php

/*
 * Author: Vika Aqordi
 * Created on 26-01-2026-09h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderEligibleGift extends RetinaAction
{
    public function handle(Order $order, array $modelData): void
    {
        dd('eligible gift update action called', $modelData);
        return;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'gift_id' => ['nullable', 'numeric'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($order, $this->validatedData);
    }
}
