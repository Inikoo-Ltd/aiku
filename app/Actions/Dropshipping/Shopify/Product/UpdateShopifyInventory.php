<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShopifyInventory
{
    use AsAction;

    public string $commandSignature = 'shopify:update-inventory';

    public function handle(): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('stock_update', true)
            ->where('platform_status', true)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                BulkUpdateShopifyPortfolio::dispatch($customerSalesChannel->id)
                    ->delay(now()->addSeconds(rand(0, 21600)));
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
