<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Order;

use App\Actions\Ordering\Order\UpdateState\CancelOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetCancelledAllegroOrdersFromApi extends RetinaAction
{
    use WithActionUpdate;

    public string $commandSignature = 'allegro:fetch-cancelled-order {customerSalesChannel}';

    public function handle(AllegroUser $allegroUser): void
    {
        $allegroOrders = $allegroUser->getOrders([
            'status' => 'CANCELLED'
        ]);

        foreach (Arr::get($allegroOrders, 'checkoutForms', []) as $allegroOrder) {
            $order = Order::where('platform_order_id', Arr::get($allegroOrder, 'id'))
                ->where('customer_id', $allegroUser->customer_id)
                ->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED])
                ->where('customer_sales_channel_id', $allegroUser->customer_sales_channel_id)
                ->first();

            if($order) {
                CancelOrder::run($order);
            }
        }
    }

    public function asCommand(Command $command)
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->firstOrFail();

        $this->handle($customerSalesChannel->user);
    }
}
