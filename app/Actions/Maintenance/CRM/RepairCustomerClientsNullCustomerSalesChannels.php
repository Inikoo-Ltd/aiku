<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\CRM;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairCustomerClientsNullCustomerSalesChannels
{
    use WithActionUpdate;


    public function handle(CustomerClient $customerClient): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('platform_id', $customerClient->platform_id)
            ->where('customer_id', $customerClient->customer_id)
            ->first();

        if (!$customerSalesChannel) {
            $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
                customer: $customerClient->customer,
                platform: $customerClient->platform,
                modelData: [
                    'reference' => (string)$customerClient->customer->id,
                ]
            );
        }


        $customerClient->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        CustomerSalesChannelsHydrateCustomerClients::run($customerSalesChannel);
    }


    public string $commandSignature = 'repair:customer_clients_null_customer_sales_channels';

    public function asCommand(Command $command): void
    {
        $count = CustomerClient::whereNull('customer_sales_channel_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        CustomerClient::orderBy('id')->whereNull('customer_sales_channel_id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
