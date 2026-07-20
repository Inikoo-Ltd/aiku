<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInWooPortfolio
{
    use AsAction;

    public string $commandSignature = 'woo:update-inventory {customerSalesChannel?}';


    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        if ($customerSalesChannel !== null) {
            if ($customerSalesChannel->status == CustomerSalesChannelStatusEnum::OPEN) {
                UpdateWooCustomerSalesChannelPortfolio::dispatch($customerSalesChannel);
            }

            return;
        }

        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        CustomerSalesChannel::query()
            ->where('platform_id', $platform->id)
            ->where('platform_status', true)
            ->where('stock_update', true)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where(function ($query) {
                $query->whereNull('ban_stock_update_util')
                    ->orWhere('ban_stock_update_util', '<=', now());
            })
            ->chunkById(200, function ($customerSalesChannels): void {
                foreach ($customerSalesChannels as $customerSalesChannel) {
                    UpdateWooCustomerSalesChannelPortfolio::dispatch($customerSalesChannel);
                }
            });
    }


    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();

        $this->handle($customerSalesChannel);
    }
}
