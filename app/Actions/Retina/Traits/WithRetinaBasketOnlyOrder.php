<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Traits;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

trait WithRetinaBasketOnlyOrder
{
    /**
     * @throws ValidationException
     */
    public function prepareForValidation(ActionRequest $request): void
    {
        $order = $request->route('order');

        if ($order instanceof Order && $order->state !== OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'messages' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }
}
