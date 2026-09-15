<?php

/*
 * Author: Artha <dev@aw-advantage.com>
 * Created: Sun, 14 Sept 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Shopify\Product\UpdateInventoryInShopifyCustomerSalesChannel;
use App\Actions\Dropshipping\Tiktok\Product\UpdateInventoryTiktokProducts;
use App\Actions\Dropshipping\Wix\Product\UpdateInventoryInWixPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queues the channel's portfolios to be pushed to the platform again. Shared by the customer's own
 * "Update stock" button and the staff "Force sync" button so both force the same work (HELP-3001).
 *
 * Returns null when nothing could be queued, which is what the callers turn into an error notice.
 */
class SyncCustomerSalesChannelPortfolios
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): ?CustomerSalesChannel
    {
        if (!$customerSalesChannel->user) {
            return null;
        }

        switch ($customerSalesChannel->platform?->type) {
            case PlatformTypeEnum::SHOPIFY:
                UpdateInventoryInShopifyCustomerSalesChannel::run($customerSalesChannel);
                break;
            case PlatformTypeEnum::WOOCOMMERCE:
                UpdateInventoryInWooPortfolio::run($customerSalesChannel, true);
                break;
            case PlatformTypeEnum::EBAY:
                UpdateInventoryInEbayPortfolio::run($customerSalesChannel, true);
                break;
            case PlatformTypeEnum::TIKTOK:
                UpdateInventoryTiktokProducts::run($customerSalesChannel, true);
                break;
            case PlatformTypeEnum::WIX:
                UpdateInventoryInWixPortfolio::run($customerSalesChannel);
                break;
            default:
                return null;
        }

        return $customerSalesChannel;
    }
}
