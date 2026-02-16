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
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateInventoryInWooPortfolio
{
    use AsAction;

    public string $commandSignature = 'woo:update-inventory';


    public function handle(?CustomerSalesChannel $customerSalesChannel = null): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        if ($customerSalesChannel === null) {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('platform_status', true)
                ->where('stock_update', true)
                ->get();
        } else {
            $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
                ->where('id', $customerSalesChannel->id)
                ->get();
        }

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->ban_stock_update_util && $customerSalesChannel->ban_stock_update_util->gt(now())) {
                continue;
            }

            if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::OPEN) {
                continue;
            }

            /** @var \App\Models\Dropshipping\WooCommerceUser $wooCommerceUser */
            $wooCommerceUser = $customerSalesChannel->user;

            if (!$wooCommerceUser) {
                continue;
            }

            $wooCommerceUser->setTimeout(20);
            $result = $wooCommerceUser->checkConnection();
            if ($result) {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => null
                ]);

                UpdateWooCustomerSalesChannelPortfolio::dispatch($customerSalesChannel);
            } else {
                $customerSalesChannel->update([
                    'ban_stock_update_util' => now()->addSeconds(10)
                ]);
            }
        }
    }


    public function asCommand(): void
    {
        $this->handle();
    }
}
