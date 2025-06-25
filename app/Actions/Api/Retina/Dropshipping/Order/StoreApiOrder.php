<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Order;

use App\Actions\Api\Retina\Dropshipping\Resource\OrderApiResource;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
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
    public function handle(CustomerSalesChannel $customerSalesChannel): Order
    {
        return StoreOrder::make()->action($customerSalesChannel->customer, [
            'platform_id' => $customerSalesChannel->platform_id,
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Order
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($this->customerSalesChannel);
    }

    public function jsonResponse(Order $order): OrderApiResource
    {
        return OrderApiResource::make($order)
            ->additional([
                'message' => __('Order created successfully'),
            ]);
    }
}
