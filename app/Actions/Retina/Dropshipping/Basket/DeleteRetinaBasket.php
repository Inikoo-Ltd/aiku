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
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaBasket extends RetinaAction
{
    public function handle(Order $order): void
    {
        $customerSalesChannel = $order->customerSalesChannel;

        if($order->transactions) {
            $order->transactions()->delete();
        }

        $order->delete();

        CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);
        CustomerHydrateBasket::dispatch($customerSalesChannel->customer);
        CustomerHydrateOrders::dispatch($customerSalesChannel->customer);
    }

    public function asController(Order $order, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($order);
    }
}
