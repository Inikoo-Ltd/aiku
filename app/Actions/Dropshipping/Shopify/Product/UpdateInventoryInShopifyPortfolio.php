<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\Concerns\AsCommand;
use Sentry;

class UpdateInventoryInShopifyPortfolio
{
    use AsCommand;

    public string $commandSignature = 'shopify:update-inventory';

    public function handle(): void
    {
        $platform              = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();
        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)->get();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
                ->whereNotNull('platform_product_variant_id')
                ->get();

            if ($customerSalesChannel->user) {
                try {
                    BulkUpdateShopifyPortfolio::run($customerSalesChannel->user, $portfolios);
                } catch (\Exception $e) {
                    Sentry::captureException($e);
                }
            }
        }
    }

    public function asCommand(): void
    {
        $this->handle();
    }
}
