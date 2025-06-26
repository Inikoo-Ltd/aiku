<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\OrderApiResource;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient): Order
    {
        return StoreOrder::make()->action($customerClient, [
            'platform_id' => $this->customerSalesChannel->platform_id,
            'customer_sales_channel_id' => $this->customerSalesChannel->id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClient $customerClient, ActionRequest $request): Order
    {
        $this->initialisationFromFulfilment($request);
        return $this->handle($customerClient);
    }

    public function jsonResponse(Order $order): OrderApiResource
    {
        return OrderApiResource::make($order)
            ->additional([
                'message' => __('Order created successfully'),
            ]);
    }
}
