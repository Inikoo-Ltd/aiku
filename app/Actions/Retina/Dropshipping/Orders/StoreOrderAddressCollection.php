<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreOrderAddressCollection extends RetinaAction
{
    /**
     * @var \App\Models\Ordering\Order
     */
    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        return UpdateOrder::make()->action($order, [
            'collection_address_id' => Arr::get($modelData, 'collection_address_id')
        ]);
    }

    public function rules(): array
    {
        return [
            'collection_address_id' => ['required', Rule::exists('addresses', 'id')],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $warehouse = $this->order->organisation?->warehouses?->first();

        if ($warehouse->address_id) {
            throw ValidationException::withMessages(['message' => __('The warehouse did not have any address.')]);
        }

        $this->set('collection_address_id', $warehouse->address_id);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
