<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairOrdersNullCustomerSalesChannels
{
    use WithActionUpdate;


    public function handle(Order $order): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('platform_id', $order->platform_id)
            ->where('customer_id', $order->customer_id)
            ->first();

        if (!$customerSalesChannel) {
            $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
                customer: $order->customer,
                platform: $order->platform,
                modelData: [
                    'reference' => (string)$order->customer->id,
                ]
            );
        }


        $order->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        CustomerSalesChannelsHydrateOrders::run($customerSalesChannel);
    }


    public string $commandSignature = 'repair:orders_null_customer_sales_channels';

    public function asCommand(Command $command): void
    {
        $count = Order::whereNull('customer_sales_channel_id')->whereNotNull('platform_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Order::orderBy('id')->whereNull('customer_sales_channel_id')->whereNotNull('platform_id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
