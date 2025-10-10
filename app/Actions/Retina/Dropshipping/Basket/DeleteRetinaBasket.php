<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Basket;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Redirect;

class DeleteRetinaBasket extends RetinaAction
{
    public function handle(Order $order): RedirectResponse
    {
        $customerSalesChannel = $order->customerSalesChannel;

        if ($order->transactions) {
            $order->transactions()->delete();
        }

        $xxx = $order;
        $order->delete();

        CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);
        CustomerHydrateBasket::run($customerSalesChannel->customer);
        CustomerHydrateOrders::dispatch($customerSalesChannel->customer);

        return Redirect::route(
            'retina.dropshipping.customer_sales_channels.basket.index',
            [
                'customerSalesChannel' => $customerSalesChannel->slug,
            ]
        )
            ->with('notification', [
                'status'  => 'info',
                'title'   => __('Success!'),
                'description' => __('Your :order has been deleted.', [
                    'order' => $xxx->reference
                ]),
            ]);
    }

    public function asController(Order $order, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($request);

        return $this->handle($order);
    }
}
