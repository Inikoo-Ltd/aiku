<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Sept 2025 22:16:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ordering;

use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use App\Rules\ValidAddress;
use App\Traits\SanitizeInputs;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderDeliveryAddress extends RetinaAction
{
    use SanitizeInputs;

    public function handle(Order $order, array $modelData): Order
    {
        return UpdateOrderDeliveryAddress::run($order, $modelData);
    }

    public function prepareForValidation(ActionRequest $request)
    {
        $this->setSanitizeFields(['address']);
        $this->sanitizeInputs();
    }

    public function rules(): array
    {
        return [
            'address'       => ['required', new ValidAddress()],
            'update_parent' => ['sometimes', 'boolean'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);

        $this->handle($order, $this->validatedData);
    }


}
