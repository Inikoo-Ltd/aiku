<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrderGiftMessage;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderGiftMessage extends RetinaAction
{
    public function handle(Order $order, array $modelData): void
    {
        UpdateOrderGiftMessage::run($order, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'has_gift_message' => ['required', 'boolean'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($order, $this->validatedData);
    }
}
