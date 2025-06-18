<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairPortfoliosNullCustomerSalesChannels
{
    use WithActionUpdate;


    public function handle(Portfolio $portfolio): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('platform_id', $portfolio->platform_id)
            ->where('customer_id', $portfolio->customer_id)
            ->first();

        if (!$customerSalesChannel) {
            $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
                customer: $portfolio->customer,
                platform: $portfolio->platform,
                modelData: [
                    'reference' => (string)$portfolio->customer->id,
                ]
            );
        }
        $portfolio->update([
            'customer_sales_channel_id' => $customerSalesChannel->id,
        ]);

        CustomerSalesChannelsHydratePortfolios::run($customerSalesChannel);
    }


    public string $commandSignature = 'repair:portfolios_null_customer_sales_channels';

    public function asCommand(Command $command): void
    {
        $count = Portfolio::whereNull('customer_sales_channel_id')->whereNotNull('platform_id')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Portfolio::orderBy('id')->whereNull('customer_sales_channel_id')->whereNotNull('platform_id')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
