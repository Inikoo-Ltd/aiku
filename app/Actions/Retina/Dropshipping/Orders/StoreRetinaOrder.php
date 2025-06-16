<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerClient $customerClient): Order
    {
        $order = StoreOrder::make()->action($customerClient, [
            'platform_id' => $customerClient->platform_id,
            'customer_sales_channel_id' => $customerClient->customer_sales_channel_id
        ]);

        CustomerSalesChannelsHydrateOrders::dispatch($customerClient->salesChannel);

        CustomerClientHydrateOrders::dispatch($customerClient);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerClient = $request->route('customerClient');
        if ($customerClient->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function htmlResponse(Order $order): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('retina.dropshipping.customer_sales_channels.basket.show', [
            $order->customerSalesChannel->slug,
            $order->slug
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerClient $customerClient, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($customerClient);
    }


}
