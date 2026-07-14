<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 22:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrderPackaging;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderPackaging extends RetinaAction
{
    public function handle(Order $order, array $modelData): Order
    {
        return UpdateOrderPackaging::make()->action($order, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'packaging_id'         => ['sometimes', 'nullable', 'integer'],
            'leaflet_ids'          => ['sometimes', 'array'],
            'leaflet_ids.*'        => ['integer'],
            'personalised_message' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public function htmlResponse(Order $order): RedirectResponse
    {
        return back();
    }
}
